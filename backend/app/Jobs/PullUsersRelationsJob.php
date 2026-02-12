<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\UsersRelation;
use App\Models\SyncState;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PullUsersRelationsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(\App\Services\CrmClient $crm)
    {
        $lock = \Illuminate\Support\Facades\Cache::lock('sync:usersrelations', 3600);

        if (!$lock->get()) {
            Log::warning('PullUsersRelationsJob: Already running, skipping.');
            return;
        }

        try {
        $state = SyncState::firstOrCreate(
            ['resource' => 'usersrelations'],
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
            $resp = $crm->post('/CrmToMobileSync/getUsersRelationMobile', [
                'updatedSince' => $since,
                'pageSize' => $limit,
                'page' => $page,
                'order' => 'WhenUpdated ASC',
                'current_LocalizationsID' => "0",
            ]);

            if ($resp->failed()) {
                Log::error("PullUsersRelationsJob: Request failed. Status: " . $resp->status());
                Log::error("PullUsersRelationsJob: Response body: " . $resp->body());
                break;
            }

            $body = $resp->json();
            
            // Debug logging
            if ($page === 1) {
                Log::info("PullUsersRelationsJob: First page response structure", [
                    'has_body_key' => isset($body['body']),
                    'response_keys' => array_keys($body ?? []),
                    'response_sample' => json_encode($body, JSON_PRETTY_PRINT),
                ]);
            }
            
            $items = $body['body'] ?? $body ?? [];
            $itemCount = is_array($items) ? count($items) : 0;
            $pageMaxDate = null;

            foreach ($items as $r) {
                if (!is_array($r)) continue;
                
                $id = (int)($r['usersRelationsID'] ?? 0);
                if (!$id) continue;

                if (isset($r['whenUpdated']) && (!$pageMaxDate || $r['whenUpdated'] > $pageMaxDate)) {
                    $pageMaxDate = $r['whenUpdated'];
                }

                UsersRelation::updateOrCreate(
                    ['UsersRelationsID' => $id],
                    [
                        'Parent_UsersID' => (int)($r['parent_UsersID'] ?? 0),
                        'UsersID' => (int)($r['usersID'] ?? 0),
                        'ParticipantRelationsDVID' => (int)($r['participantRelationsDVID'] ?? 0),
                        'Description' => (string)($r['description'] ?? ''),
                        'DateFrom' => $this->validateDate($r['dateFrom'] ?? '', null),
                        'DateTo' => $this->validateDate($r['dateTo'] ?? '', null),
                        'Cancelled' => (int)($r['cancelled'] ?? 0),
                        'WhenInserted' => $this->validateDate($r['whenInserted'] ?? now(), now()),
                        'WhoInserted_UsersID' => (int)($r['whoInserted_UsersID'] ?? 0),
                        'WhenUpdated' => $this->validateDate($r['whenUpdated'] ?? now(), now()),
                        'WhoUpdated_UsersID' => (int)($r['whoUpdated_UsersID'] ?? 0),
                        'LocalizationsID' => (int)($r['localizationsID'] ?? 0),
                        'Status' => (int)($r['status'] ?? 0),
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

        Log::info("PullUsersRelationsJob: completed. Total processed: {$totalProcessed}");
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
