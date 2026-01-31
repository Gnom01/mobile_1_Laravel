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
        $state = \App\Models\SyncState::firstOrCreate(
            ['resource' => 'users'],
            ['last_sync_at' => now()->subYears(5)]
        );

        $since = $state->last_sync_at ? $state->last_sync_at->format('Y-m-d H:i:s') : null;
        $resp = $crm->post('/Users/getPage', [
            'updatedSince' => $since,
            'limit' => 500,
             'page' => 1,
            'order' => 'WhenUpdated ASC', 
            'current_LocalizationsID' => "0",
        ])->json();

        $items = $resp['body'] ?? $resp ?? [];
        $maxDate = null;

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
        }

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
