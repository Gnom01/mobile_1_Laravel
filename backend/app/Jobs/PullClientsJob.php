<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PullClientsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(\App\Services\CrmClient $crm)
    {
        $state = \App\Models\SyncState::firstOrCreate(
            ['resource' => 'clients'],
            ['last_sync_at' => now()->subYears(5), 'is_full_synced' => false]
        );

        // Jesli nie jest w pelni zsynchronizowany, startujemy od zera (full sync)
        // W przeciwnym razie robimy delte z bezpiecznym marginesem (minus 1 sekunda)
        if (!$state->is_full_synced) {
            $since = null;
            $state->full_sync_started_at = now();
            $state->save();
        } else {
            // Bezpieczny wzorzec: cofamy since o 1 sekunde, aby nie pominac rekordow o tej samej dacie
            $since = $state->last_sync_at 
                ? $state->last_sync_at->subSecond()->format('Y-m-d H:i:s') 
                : null;
        }

        $page = 1;
        $limit = 500;
        $maxDate = null;
        $totalProcessed = 0;

        do {
            $resp = $crm->post('/Clients/getPage', [
                'updatedSince' => $since,
                'limit' => $limit,
                'page' => $page,
                'order' => 'WhenUpdated ASC',
                'current_LocalizationsID' => "0",
            ])->json();

            $items = $resp['body'] ?? $resp ?? [];
            $itemCount = is_array($items) ? count($items) : 0;
            
            foreach ($items as $r) {
                if (!is_array($r)) continue;

                $clientsID = (int)($r['clientsID'] ?? 0);
                $whenUpdated = $this->validateDate($r['whenUpdated'] ?? '', now());

                // Mapowanie daty do kursora
                if ($r['whenUpdated'] && (!$maxDate || $r['whenUpdated'] > $maxDate)) {
                    $maxDate = $r['whenUpdated'];
                }

                \App\Models\Client::updateOrCreate(
                    ['ClientsID' => $clientsID], 
                    [
                        'Parent_ClientsID' => (int)($r['parent_ClientsID'] ?? 0),
                        'GUID' => (string)($r['guid'] ?? ''),
                        'ClientName' => (string)($r['clientName'] ?? ''),
                        'NIP' => (string)($r['nip'] ?? ''),
                        'DIK' => (string)($r['dik'] ?? ''),
                        'City' => (string)($r['city'] ?? ''),
                        'ZipCode' => (string)($r['zipCode'] ?? ''),
                        'Address' => (string)($r['address'] ?? ''),
                        'Longitude' => (float)($r['longitude'] ?? 0),
                        'Latitude' => (float)($r['latitude'] ?? 0),
                        'Phone' => (string)($r['phone'] ?? ''),
                        'Logo' => (string)($r['logo'] ?? ''),
                        'URL' => (string)($r['url'] ?? ''),
                        'EMAIL' => (string)($r['email'] ?? ''),
                        'TransferID' => 0,
                        'Cancelled' => (int)($r['cancelled'] ?? 0),
                        'Admin' => (int)($r['admin'] ?? 0),
                        'WhenInserted' => $this->validateDate($r['whenInserted'] ?? '', now()),
                        'WhoInserted_UsersID' => (int)($r['whoInserted_UsersID'] ?? 0),
                        'WhenUpdated' => $whenUpdated,
                        'WhoUpdated_UsersID' => (int)($r['whoUpdated_UsersID'] ?? 0),
                        'Regon' => (string)($r['regon'] ?? ''),
                        'ContractHeader' => (string)($r['contractHeader'] ?? ''),
                        'ClientsCyti' => (string)($r['clientsCyti'] ?? ''),
                        'P24_Login' => (string)($r['P24_Login'] ?? ''),
                        'P24_CRC' => (string)($r['P24_CRC'] ?? ''),
                        'P24_Reports' => (string)($r['P24_Reports'] ?? ''),
                    ]
                );
                
                $totalProcessed++;
            }

            $page++;
            
        } while ($itemCount >= $limit);

        \Illuminate\Support\Facades\Log::info("PullClientsJob: completed. Total processed: {$totalProcessed}");

        if ($maxDate) {
            $state->last_sync_at = \Carbon\Carbon::parse($maxDate);
        }

        if (!$state->is_full_synced) {
            $state->is_full_synced = true;
            $state->full_sync_completed_at = now();
        }

        $state->save();
    }

    private function validateDate($date, $default = null)
    {
        if (empty($date)) {
            return $default;
        }
        if (str_starts_with($date, '0000') || str_starts_with($date, '-')) {
            return $default;
        }
        return $date;
    }

}
