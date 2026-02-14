<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Client;
use App\Services\CrmSyncService;

class PullClientsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService)
    {
        $syncService->sync([
            'resource'   => 'clients',
            'endpoint'   => '/Clients/getPage',
            'model'      => Client::class,
            'primaryKey'    => 'ClientsID',
            'apiPrimaryKey' => 'clientsID',
            'pageSize'      => 500,
            'pageSizeParam' => 'limit',
            'responseKey'   => 'body',
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],
            'fieldMap' => function (array $r) use ($syncService) {
                return [
                    'Parent_ClientsID'      => (int)($r['parent_ClientsID'] ?? 0),
                    'GUID'                  => (string)($r['guid'] ?? ''),
                    'ClientName'            => (string)($r['clientName'] ?? ''),
                    'NIP'                   => (string)($r['nip'] ?? ''),
                    'DIK'                   => (string)($r['dik'] ?? ''),
                    'City'                  => (string)($r['city'] ?? ''),
                    'ZipCode'               => (string)($r['zipCode'] ?? ''),
                    'Address'               => (string)($r['address'] ?? ''),
                    'Longitude'             => (float)($r['longitude'] ?? 0),
                    'Latitude'              => (float)($r['latitude'] ?? 0),
                    'Phone'                 => (string)($r['phone'] ?? ''),
                    'Logo'                  => (string)($r['logo'] ?? ''),
                    'URL'                   => (string)($r['url'] ?? ''),
                    'EMAIL'                 => (string)($r['email'] ?? ''),
                    'TransferID'            => 0,
                    'Cancelled'             => (int)($r['cancelled'] ?? 0),
                    'Admin'                 => (int)($r['admin'] ?? 0),
                    'WhenInserted'          => $syncService->validateDate($r['whenInserted'] ?? '', now()),
                    'WhoInserted_UsersID'   => (int)($r['whoInserted_UsersID'] ?? 0),
                    'WhenUpdated'           => $syncService->validateDate($r['whenUpdated'] ?? '', now()),
                    'WhoUpdated_UsersID'    => (int)($r['whoUpdated_UsersID'] ?? 0),
                    'Regon'                 => (string)($r['regon'] ?? ''),
                    'ContractHeader'        => (string)($r['contractHeader'] ?? ''),
                    'ClientsCyti'           => (string)($r['clientsCyti'] ?? ''),
                    'P24_Login'             => (string)($r['P24_Login'] ?? ''),
                    'P24_CRC'               => (string)($r['P24_CRC'] ?? ''),
                    'P24_Reports'           => (string)($r['P24_Reports'] ?? ''),
                ];
            },
        ]);
    }
}
