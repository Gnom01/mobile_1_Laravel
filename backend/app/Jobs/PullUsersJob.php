<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\CrmUser;
use App\Services\CrmSyncService;
use Illuminate\Support\Str;

class PullUsersJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService)
    {
        $syncService->sync([
            'resource'   => 'users',
            'endpoint'   => '/CrmToMobileSync/getUsersMobile',
            'model'      => CrmUser::class,
            'primaryKey'    => 'UsersID',
            'apiPrimaryKey' => 'usersID',
            'pageSize'   => 1000,
            'responseKey' => 'body',
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],
            'fieldMap' => function (array $r) use ($syncService) {
                $guid = (string)($r['guid'] ?? '');
                if (empty($guid)) {
                    $guid = (string) Str::uuid();
                }

                return [
                    'guid'                              => $guid,
                    'LastName'                          => (string)($r['lastName'] ?? ''),
                    'FirstName'                         => (string)($r['firstName'] ?? ''),
                    'Login'                             => (string)($r['login'] ?? ''),
                    'Email'                             => (string)($r['email'] ?? ''),
                    'Password'                          => (string)($r['password'] ?? ''),
                    'PassLenght'                        => (int)($r['passLenght'] ?? 0),
                    'RolesID'                           => (int)($r['rolesID'] ?? 1),
                    'ClientsID'                         => (int)($r['clientsID'] ?? 0),
                    'UserStatus'                        => (int)($r['userStatus'] ?? 1),
                    'Hash'                              => (string)($r['hash'] ?? ''),
                    'Active'                            => (int)($r['active'] ?? 0),
                    'ActivationDate'                    => $syncService->validateDate($r['activationDate'] ?? null, null),
                    'NumberOfLogins'                    => (int)($r['numberOfLogins'] ?? 0),
                    'PassResetToken'                    => (string)($r['passResetToken'] ?? ''),
                    'PassResetExpiration'               => $syncService->validateDate($r['passResetExpiration'] ?? '', '1999-12-31 00:00:00'),
                    'JobTitle'                          => (string)($r['jobTitle'] ?? ''),
                    'Phone'                             => (string)($r['phone'] ?? ''),
                    'Room'                              => (string)($r['room'] ?? ''),
                    'WebSite'                           => (string)($r['webSite'] ?? ''),
                    'Newsletter'                        => (int)($r['newsletter'] ?? 0),
                    'RequestedCompanyName'              => (string)($r['requestedCompanyName'] ?? ''),
                    'Description'                       => (string)($r['description'] ?? ''),
                    'Cancelled'                         => (int)($r['cancelled'] ?? 0),
                    'WhenInserted'                      => $syncService->validateDate($r['whenInserted'] ?? '', now()),
                    'WhoInserted_UsersID'               => (int)($r['whoInserted_UsersID'] ?? 0),
                    'WhenUpdated'                       => $syncService->validateDate($r['whenUpdated'] ?? '', now()),
                    'WhoUpdated_UsersID'                => (int)($r['whoUpdated_UsersID'] ?? 0),
                    'Default_LocalizationsID'           => (int)($r['default_LocalizationsID'] ?? 0),
                    'DateOfBirdth'                      => $syncService->validateDate($r['dateOfBirdth'] ?? '', null),
                    'BirthPlace'                        => (string)($r['birthPlace'] ?? ''),
                    'Street'                            => (string)($r['street'] ?? ''),
                    'Building'                          => (string)($r['building'] ?? ''),
                    'Flat'                              => (string)($r['flat'] ?? ''),
                    'City'                              => (string)($r['city'] ?? ''),
                    'PostalCode'                        => (string)($r['postalCode'] ?? ''),
                    'PostPlace'                         => (string)($r['postPlace'] ?? ''),
                    'VoivodeshipDVID'                   => (int)($r['voivodeshipDVID'] ?? 0),
                    'District'                          => (string)($r['district'] ?? ''),
                    'Comunity'                          => (string)($r['comunity'] ?? ''),
                    'GenderDVID'                        => (int)($r['genderDVID'] ?? 0),
                    'MemberCardNumber'                  => (string)($r['memberCardNumber'] ?? ''),
                    'IdentityNumber'                    => (string)($r['identityNumber'] ?? ''),
                    'Pesel'                             => (string)($r['pesel'] ?? ''),
                    'PersonalDataProcessingConsent'     => (int)($r['personalDataProcessingConsent'] ?? 0),
                    'consentReceiveSmsEmailPhone'       => (int)($r['consentReceiveSmsEmailPhone'] ?? 0),
                    'marketingAgreement'                => (int)($r['marketingAgreement'] ?? 0),
                    'PaymentMethodsDVID'                => (int)($r['paymentMethodsDVID'] ?? 0),
                    'Parent_UsersID'                    => isset($r['parent_UsersID']) ? (int)$r['parent_UsersID'] : null,
                    'FileName'                          => (string)($r['fileName'] ?? ''),
                    'FileExtension'                     => (string)($r['fileExtension'] ?? ''),
                    'bankAccount'                       => (string)($r['bankAccount'] ?? ''),
                    'entryFee'                          => (int)($r['entryFee'] ?? 0),
                ];
            },
        ]);
    }
}
