<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\UsersPaymentsSchedule;
use App\Models\SyncState;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PullPaymentsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(\App\Services\CrmClient $crm)
    {
        $lock = \Illuminate\Support\Facades\Cache::lock('sync:payments', 3600);

        if (!$lock->get()) {
            Log::warning('PullPaymentsJob: Already running, skipping.');
            return;
        }

        try {
        ini_set('memory_limit', '512M');
        
        $state = SyncState::firstOrCreate(
            ['resource' => 'payments'],
            ['last_sync_at' => null, 'is_full_synced' => false]
        );

        if (!$state->is_full_synced && !$state->full_sync_started_at) {
            $since = null;
            $state->full_sync_started_at = now();
            $state->save();
        } else {
            $since = $state->last_sync_at 
                ? $state->last_sync_at->subSecond()->format('Y-m-d H:i:s') 
                : null;
        }

        $page = 1;
        $limit = 1000;
        $totalProcessed = 0;

        do {
            $resp = $crm->post('/CrmToMobileSync/getUserspaymentsschedulesMobile', [
                'updatedSince' => $since,
                'pageSize' => $limit,
                'page' => $page,
                'order' => 'WhenUpdated ASC',
                'current_LocalizationsID' => "0",
            ]);

            if ($resp->failed()) {
                Log::error("PullPaymentsJob: Request failed. Status: " . $resp->status());
                Log::error("PullPaymentsJob: Response body: " . $resp->body());
                break;
            }

            $body = $resp->json();
            
            // Debug logging
            if ($page === 1) {
                Log::info("PullPaymentsJob: First page response structure", [
                    'has_body_key' => isset($body['body']),
                    'response_keys' => array_keys($body ?? []),
                    'response_sample' => json_encode($body, JSON_PRETTY_PRINT),
                ]);
            }
            
            $items = $body['body'] ?? $body ?? [];
            $itemCount = is_array($items) ? count($items) : 0;
            $pageMaxDate = null;

            Log::info("PullPaymentsJob: Page {$page} fetched {$itemCount} items.");

            foreach ($items as $r) {
                if (!is_array($r)) continue;
                
                $id = (int)($r['usersPaymentsSchedulesID'] ?? 0);
                if (!$id) continue;

                if (isset($r['whenUpdated']) && (!$pageMaxDate || $r['whenUpdated'] > $pageMaxDate)) {
                    $pageMaxDate = $r['whenUpdated'];
                }

                UsersPaymentsSchedule::updateOrCreate(
                    ['usersPaymentsSchedulesID' => $id],
                    [
                        'usersID' => (int)($r['usersID'] ?? 0),
                        'contractsID' => (int)($r['contractsID'] ?? 0),
                        'productsID' => (int)($r['productsID'] ?? 0),
                        'coursesHeadingsID' => (int)($r['coursesHeadingsID'] ?? 0),
                        'instalmentNumber' => (int)($r['instalmentNumber'] ?? 0),
                        'contractInstalmentNumber' => (int)($r['contractInstalmentNumber'] ?? 0),
                        'voidInstalment' => (int)($r['voidInstalment'] ?? 0),
                        'positionName' => (string)($r['positionName'] ?? $r['productName'] ?? ''),
                        'productAvailableFromDate' => (string)($r['productAvailableFromDate'] ?? ''),
                        'productAvailableToDate' => (string)($r['productAvailableToDate'] ?? ''),
                        'lessonsAreCounted' => (int)($r['lessonsAreCounted'] ?? 0),
                        'lessonsRemainingForUse' => (int)($r['lessonsRemainingForUse'] ?? 0),
                        'paymentDate' => $this->validateDate($r['paymentDate'] ?? '', null),
                        'paymentAmount' => (float)($r['paymentAmount'] ?? 0),
                        'paymentStatusesDVID' => (int)($r['paymentStatusesDVID'] ?? 1),
                        'paymentMethodDVIDList' => (string)($r['paymentMethodDVIDList'] ?? '0'),
                        'amountPaid' => (float)($r['amountPaid'] ?? 0),
                        'amountTransferred' => (float)($r['amountTransferred'] ?? 0),
                        'amountCorrected' => (float)($r['amountCorrected'] ?? 0),
                        'comments' => (string)($r['comments'] ?? ''),
                        'localizationsID' => (int)($r['localizationsID'] ?? 0),
                        'cancelled' => (int)($r['cancelled'] ?? 0),
                        'whenInserted' => $this->validateDate($r['whenInserted'] ?? '', now()),
                        'whoInserted_UsersID' => (int)($r['whoInserted_UsersID'] ?? 0),
                        'whenUpdated' => $this->validateDate($r['whenUpdated'] ?? '', now()),
                        'whoUpdated_UsersID' => (int)($r['whoUpdated_UsersID'] ?? 0),
                        'price' => (float)($r['price'] ?? 0),
                        'usersProductsID' => (int)($r['usersProductsID'] ?? 0),
                        'lastPaymentDate' => $this->validateDate($r['lastPaymentDate'] ?? '', null),
                        'processesDVID' => (int)($r['processesDVID'] ?? 0),
                        'payer_UsersID' => (int)($r['payer_UsersID'] ?? 0),
                        'paymentMethodDVID' => (string)($r['paymentMethodDVID'] ?? ''),
                    ]
                );
                
                $totalProcessed++;
            }

            if ($pageMaxDate) {
                $state->last_sync_at = Carbon::parse($pageMaxDate);
                $state->save();
            }

            $page++;
            
        } while ($itemCount >= $limit);

        if (!$state->is_full_synced && $totalProcessed > 0) {
            $state->is_full_synced = true;
            $state->full_sync_completed_at = now();
            $state->save();
        }

        Log::info("PullPaymentsJob: completed. Total processed: {$totalProcessed}");
        } finally {
            $lock->forceRelease();
        }
    }

    private function validateDate($date, $default = null)
    {
        if (empty($date)) return $default;
        if (str_starts_with($date, '0000') || str_starts_with($date, '-')) return $default;
        return $date;
    }
}
