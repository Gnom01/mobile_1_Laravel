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
            'pageSizeParam' => 'limit',
            'responseKey'   => 'body',
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],
            
            'fieldMap' => function (array $r) use ($syncService) {
                return [
                    'ClientsID'               => (int)($r['clientsID'] ?? 0),
                    'LocalizationName'        => (string)($r['localizationName'] ?? ''),
                    'Address'                 => (string)($r['address'] ?? ''),
                    'ZipCode'                => (string)($r['zipCode'] ?? ''),
                    'City'                   => (string)($r['city'] ?? ''),
                    'EMail'                  => (string)($r['eMail'] ?? ''),
                    'PhoneNumber'            => (string)($r['phoneNumber'] ?? ''),
                    'NumberOfClassRooms'     => (int)($r['numberOfClassRooms'] ?? 0),
                    'Description'            => (string)($r['description'] ?? ''),
                    'Cancelled'              => (int)($r['cancelled'] ?? 0),
                    'Hidden'                 => (int)($r['hidden'] ?? 0),
                    'WhenInserted'           => $syncService->validateDate($r['whenInserted'] ?? '', now()),
                    'WhoInserted_UsersID'    => (int)($r['whoInserted_UsersID'] ?? 0),
                    'WhenUpdated'            => $syncService->validateDate($r['whenUpdated'] ?? '', now()),
                    'WhoUpdated_UsersID'     => (int)($r['whoUpdated_UsersID'] ?? 0),
                    'LocalizationCode'       => (string)($r['localizationCode'] ?? ''),
                    'BanckAccountNumber'     => (string)($r['banckAccountNumber'] ?? ''),
                    'Default_VatRatesIK'     => (string)($r['default_VatRatesIK'] ?? ''),
                    'LedgerLocalizationCode' => (string)($r['ledgerLocalizationCode'] ?? ''),
                    'SchoolCyti'             => (string)($r['schoolCyti'] ?? ''),
                    'P24_Login'              => (string)($r['p24_Login'] ?? ''),
                    'P24_CRC'                => (string)($r['p24_CRC'] ?? ''),
                    'P24_Reports'            => (string)($r['p24_Reports'] ?? ''),
                    'TransferID'             => (int)($r['transferID'] ?? 0),
                    'SelectPaymentForm'      => (string)($r['selectPaymentForm'] ?? ''),
                    'KeyMpayApi'             => (string)($r['keyMpayApi'] ?? ''),
                    'KeyMpay'                => (string)($r['keyMpay'] ?? ''),
                    'fiskalType'             => (string)($r['fiskalType'] ?? ''),
                    'eElientIdFiskal'        => (string)($r['eElientIdFiskal'] ?? ''),
                    'eClientSecretFiskal'    => (string)($r['eClientSecretFiskal'] ?? ''),
                    'ePostIdFiscal'          => (string)($r['ePostIdFiscal'] ?? ''),
                ];
            },
        ]);
    }
}
