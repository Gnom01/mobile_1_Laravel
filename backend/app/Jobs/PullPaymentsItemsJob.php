<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\PaymentItem;
use App\Services\CrmSyncService;

class PullPaymentsItemsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService)
    {
        $syncService->sync([
            'resource'   => 'paymentsitems',
            'endpoint'   => '/CrmToMobileSync/getPaymentsItemsMobile',
            'model'      => PaymentItem::class,
            'primaryKey' => 'paymentsItemsID',
            'pageSize'   => 1000,
            'responseKey'   => 'body',
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],
            'fieldMap' => function (array $r) use ($syncService) {
                return [
                    'clientsID'                   => (int)($r['clientsID'] ?? 0),
                    'localizationsID'            => (int)($r['localizationsID'] ?? 0),
                    'usersID'                    => (int)($r['usersID'] ?? 0),
                    'payer_usersID'              => (int)($r['payer_usersID'] ?? 0),
                    'usersBasketsID'             => (int)($r['usersBasketsID'] ?? 0),
                    'usersPaymentsSchedulesID'   => (int)($r['usersPaymentsSchedulesID'] ?? 0),
                    'contractsID'                => (int)($r['contractsID'] ?? 0),
                    'productsID'                 => (int)($r['productsID'] ?? 0),
                    'paymentDate'                => $syncService->validateDate($r['paymentDate'] ?? '', null),
                    'itemName'                   => (string)($r['itemName'] ?? ''),
                    'productUnitPrice'           => (float)($r['productUnitPrice'] ?? 0),
                    'productQuantity'            => (float)($r['productQuantity'] ?? 0),
                    'productUnitIK'              => (string)($r['productUnitIK'] ?? ''),
                    'paymentItemAmount'          => (float)($r['paymentItemAmount'] ?? 0),
                    'vatAmount'                  => (float)($r['vatAmount'] ?? 0),
                    'vatRatesIK'                 => (string)($r['vatRatesIK'] ?? ''),
                    'ptu'                        => (string)($r['ptu'] ?? ''),
                    'paymentsID'                 => (int)($r['paymentsID'] ?? 0),
                    'cancelled'                  => (int)($r['cancelled'] ?? 0),
                    'whenInserted'               => $syncService->validateDate($r['whenInserted'] ?? '', now()),
                    'whoInserted_UsersID'        => (int)($r['whoInserted_UsersID'] ?? 0),
                    'whenUpdated'                => $syncService->validateDate($r['whenUpdated'] ?? '', now()),
                    'whoUpdated_UsersID'         => (int)($r['whoUpdated_UsersID'] ?? 0),
                    'descriptions'               => (string)($r['descriptions'] ?? ''),
                    'tranfer_PaymentsItemsID'    => (int)($r['tranfer_PaymentsItemsID'] ?? 0),
                    'reduceFromWallet_UsersID'   => (int)($r['reduceFromWallet_UsersID'] ?? 0),
                ];
            },
        ]);
    }
}
