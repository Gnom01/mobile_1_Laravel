<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Localization;
use App\Services\CrmSyncService;

class PullLocalizationsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService)
    {
        $syncService->sync([
            'resource'   => 'localizations',
            'endpoint'   => '/CrmToMobileSync/getLocalizationsMobile',
            'model'      => Localization::class,
            'primaryKey' => 'LocalizationsID',
            'pageSize'   => 1000,
            'responseKey'   => 'body',
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],
            
            'fieldMap' => function (array $r) use ($syncService) {
                $int = static fn (array $data, string $key): int => array_key_exists($key, $data) ? (int) $data[$key] : 0;
                $str = static fn (array $data, string $key): string => array_key_exists($key, $data) ? (string) $data[$key] : '';
                $strAny = static function (array $data, array $keys): string {
                    foreach ($keys as $key) {
                        if (array_key_exists($key, $data)) {
                            return (string) $data[$key];
                        }
                    }

                    return '';
                };
                $intAny = static function (array $data, array $keys): int {
                    foreach ($keys as $key) {
                        if (array_key_exists($key, $data)) {
                            return (int) $data[$key];
                        }
                    }

                    return 0;
                };

                return [
                    'ClientsID'               => $intAny($r, ['ClientsID', 'clientsID']),
                    'LocalizationName'        => $strAny($r, ['LocalizationName', 'localizationName']),
                    'Address'                 => $strAny($r, ['Address', 'address']),
                    'ZipCode'                 => $strAny($r, ['ZipCode', 'zipCode']),
                    'City'                    => $strAny($r, ['City', 'city']),
                    'EMail'                   => $strAny($r, ['EMail', 'eMail', 'email']),
                    'PhoneNumber'             => $strAny($r, ['PhoneNumber', 'phoneNumber']),
                    'NumberOfClassRooms'      => $intAny($r, ['NumberOfClassRooms', 'numberOfClassRooms']),
                    'Description'             => $strAny($r, ['Description', 'description']),
                    'Cancelled'               => $intAny($r, ['Cancelled', 'cancelled']),
                    'Hidden'                  => $intAny($r, ['Hidden', 'hidden']),
                    'WhenInserted'            => $syncService->validateDate($strAny($r, ['WhenInserted', 'whenInserted']), now()),
                    'WhoInserted_UsersID'     => $intAny($r, ['WhoInserted_UsersID', 'whoInserted_UsersID']),
                    'WhenUpdated'             => $syncService->validateDate($strAny($r, ['WhenUpdated', 'whenUpdated']), now()),
                    'WhoUpdated_UsersID'      => $intAny($r, ['WhoUpdated_UsersID', 'whoUpdated_UsersID']),
                    'LocalizationCode'        => $strAny($r, ['LocalizationCode', 'localizationCode']),
                    'BanckAccountNumber'      => $strAny($r, ['BanckAccountNumber', 'banckAccountNumber']),
                    'Default_VatRatesIK'      => $strAny($r, ['Default_VatRatesIK', 'default_VatRatesIK']),
                    'LedgerLocalizationCode'  => $strAny($r, ['LedgerLocalizationCode', 'ledgerLocalizationCode']),
                ];
            },
        ]);
    }
}