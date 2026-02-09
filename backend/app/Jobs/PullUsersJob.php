<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PullUsersJob implements ShouldQueue
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
        // Increase memory limit to prevent exhaustion
        ini_set('memory_limit', '512M');
        
        $state = \App\Models\SyncState::firstOrCreate(
            ['resource' => 'users'],
            ['last_sync_at' => now()->subYears(5)]
        );

        $since = $state->last_sync_at ? $state->last_sync_at->format('Y-m-d H:i:s') : null;
        
        $page = 1;
        $limit = 100; // Reduced from 500 to prevent memory issues
        $maxDate = null;
        $totalProcessed = 0;

        do {
            $resp = $crm->post('/Users/getUsersSynchMobile', [
                'updatedSince' => $since,
                'pageSize' => $limit,
                'page' => $page,
                'order' => 'WhenUpdated ASC', 
                'current_LocalizationsID' => "0",
            ])->json();

            $items = $resp['body'] ?? $resp ?? [];
            $itemCount = is_array($items) ? count($items) : 0;

            foreach ($items as $r) {
                
                if (!is_array($r)) continue;
                
                if (isset($r['whenUpdated']) && (!$maxDate || $r['whenUpdated'] > $maxDate)) {
                    $maxDate = $r['whenUpdated'];
                }
                
                \App\Models\CrmUser::updateOrCreate(
                    ['UsersID' => (int)($r['usersID'] ?? 0)],
                    [
                        'LastName' => (string)($r['lastName'] ?? ''),
                        'FirstName' => (string)($r['firstName'] ?? ''),
                        'Login' => (string)($r['login'] ?? ''),
                        'Email' => (string)($r['email'] ?? ''),
                        'Password' => (string)($r['password'] ?? ''),
                        'PassLenght' => (int)($r['passLenght'] ?? 0),
                        'RolesID' => (int)($r['rolesID'] ?? 1),
                        'ClientsID' => (int)($r['clientsID'] ?? 0),
                        'UserStatus' => (int)($r['userStatus'] ?? 1),
                        'Hash' => (string)($r['hash'] ?? ''),
                        'Active' => (int)($r['active'] ?? 0),
                        'ActivationDate' => $this->validateDate($r['activationDate'] ?? null, null),
                        'NumberOfLogins' => (int)($r['numberOfLogins'] ?? 0),
                        'PassResetToken' => (string)($r['passResetToken'] ?? ''),
                        'PassResetExpiration' => $this->validateDate($r['passResetExpiration'] ?? '', '1999-12-31 00:00:00'),
                        'JobTitle' => (string)($r['jobTitle'] ?? ''),
                        'Phone' => (string)($r['phone'] ?? ''),
                        'Room' => (string)($r['room'] ?? ''),
                        'WebSite' => (string)($r['webSite'] ?? ''),
                        'Newsletter' => (int)($r['newsletter'] ?? 0),
                        'RequestedCompanyName' => (string)($r['requestedCompanyName'] ?? ''),
                        'Description' => (string)($r['description'] ?? ''),
                        'Cancelled' => (int)($r['cancelled'] ?? 0),
                        'WhenInserted' => $this->validateDate($r['whenInserted'] ?? '', now()),
                        'WhoInserted_UsersID' => (int)($r['whoInserted_UsersID'] ?? 0),
                        'WhenUpdated' => $this->validateDate($r['whenUpdated'] ?? '', now()),
                        'WhoUpdated_UsersID' => (int)($r['whoUpdated_UsersID'] ?? 0),
                        'Default_LocalizationsID' => (int)($r['default_LocalizationsID'] ?? 0),
                        'DateOfBirdth' => $this->validateDate($r['dateOfBirdth'] ?? '', null),
                        'BirthPlace' => (string)($r['birthPlace'] ?? ''),
                        'Street' => (string)($r['street'] ?? ''),
                        'Building' => (string)($r['building'] ?? ''),
                        'Flat' => (string)($r['flat'] ?? ''),
                        'City' => (string)($r['city'] ?? ''),
                        'PostalCode' => (string)($r['postalCode'] ?? ''),
                        'Pesel' => (string)($r['pesel'] ?? ''),
                        'Parent_UsersID' => null,
                        'FileName' => (string)($r['fileName'] ?? ''),
                        'FileExtension' => (string)($r['fileExtension'] ?? ''),
                        'bankAccount' => (string)($r['bankAccount'] ?? ''),
                        'entryFee' => (int)($r['entryFee'] ?? 0),
                    ]
                );
                
                $totalProcessed++;
            }

            $page++;
            
            // Jeśli dostaliśmy mniej niż limit, to była ostatnia strona
        } while ($itemCount >= $limit);

        \Illuminate\Support\Facades\Log::info("PullUsersJob: completed. Total processed: {$totalProcessed}");

        if ($maxDate) {
            $state->last_sync_at = \Carbon\Carbon::parse($maxDate);
            $state->save();
        } elseif (!$state->last_sync_at) {
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
