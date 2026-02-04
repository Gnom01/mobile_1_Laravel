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
            ['last_sync_at' => now()->subYears(5)]
        );

        $since = $state->last_sync_at ? $state->last_sync_at->format('Y-m-d H:i:s') : null;

        $page = 1;
        $limit = 500;
        $maxDate = null;
        $totalProcessed = 0;

        do {
            \Illuminate\Support\Facades\Log::info("PullClientsJob: fetching page {$page} from /Clients/getPage since [{$since}]...");

            $resp = $crm->post('/Clients/getPage', [
                'updatedSince' => $since,
                'limit' => $limit,
                'page' => $page,
                'order' => 'WhenUpdated ASC',
                'current_LocalizationsID' => "0",
            ])->json();

            $items = $resp['body'] ?? $resp ?? [];
            $itemCount = is_array($items) ? count($items) : 0;
            
            \Illuminate\Support\Facades\Log::info("PullClientsJob: page {$page} fetched {$itemCount} items.");

            foreach ($items as $r) {
                if (!is_array($r)) continue;

                // Mapowanie daty do kursora (klucze z małej litery w JSON)
                if (isset($r['whenUpdated']) && (!$maxDate || $r['whenUpdated'] > $maxDate)) {
                    $maxDate = $r['whenUpdated'];
                }

                \App\Models\Client::updateOrCreate(
                    ['ClientsID' => (int)($r['clientsID'] ?? 0)], 
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
                        'TransferID' => 0, // brak w JSON
                        'Cancelled' => (int)($r['cancelled'] ?? 0),
                        'Admin' => (int)($r['admin'] ?? 0),
                        'WhenInserted' => $this->validateDate($r['whenInserted'] ?? '', now()),
                        'WhoInserted_UsersID' => (int)($r['whoInserted_UsersID'] ?? 0),
                        'WhenUpdated' => $this->validateDate($r['whenUpdated'] ?? '', now()),
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
            
            // Jeśli dostaliśmy mniej niż limit, to była ostatnia strona
        } while ($itemCount >= $limit);

        \Illuminate\Support\Facades\Log::info("PullClientsJob: completed. Total processed: {$totalProcessed}");

        // Jeśli pobraliśmy dane, przesuwamy kursor na datę OSTATNIEGO rekordu (bo sortujemy ASC)
        // Jeśli nie pobraliśmy nic => jesteśmy na bieżąco (opcjonalnie można dać now())
        if ($maxDate) {
            $state->last_sync_at = \Carbon\Carbon::parse($maxDate);
            $state->save();
        } elseif (!$state->last_sync_at) {
             // Jeśli nigdy nie było synchro i przyszło 0, to ustaw np. today? 
             // Albo zostaw null, żeby próbował od początku wieków przy następnym razie
             $state->last_sync_at = now();
             $state->save();
        }
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
