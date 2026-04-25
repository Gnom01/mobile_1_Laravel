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

                return [
                    'ClientsID'               => $r["ClientsID"],
                    'LocalizationName'        => $r["LocalizationName"],
                    'Address'                 => $r["Address"],
                    'ZipCode'                 => $r["ZipCode"],
                    'City'                    => $r["City"],
                    'EMail'                   => $r["EMail"],
                    'PhoneNumber'             => $r["PhoneNumber"],
                    'NumberOfClassRooms'      => $r["NumberOfClassRooms"],
                    'Description'             => $r["Description"],
                    'Cancelled'               => $r["Cancelled"],
                    'Hidden'                  => $r["Hidden"],
                    'WhenInserted'            => $syncService->validateDate(isset($r['WhenInserted']) ? $r['WhenInserted'] : '', now()),
                    'WhoInserted_UsersID'     => $r["WhoInserted_UsersID"],
                    'WhenUpdated'             => $syncService->validateDate(isset($r['WhenUpdated']) ? $r['WhenUpdated'] : '', now()),
                    'WhoUpdated_UsersID'      => $r["WhoUpdated_UsersID"],
                    'LocalizationCode'        => $r["LocalizationCode"],
                    'BanckAccountNumber'      => $r["BanckAccountNumber"],
                    'Default_VatRatesIK'      => $r["Default_VatRatesIK"],
                    'LedgerLocalizationCode'  => $r["LedgerLocalizationCode"],
                ];
            },
        ]);
    }
}