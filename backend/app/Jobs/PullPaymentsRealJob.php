<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Payment;
use App\Services\CrmSyncService;

class PullPaymentsRealJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService)
    {
        $syncService->sync([
            'resource'   => 'payments_real',
            'endpoint'   => '/CrmToMobileSync/getPaymentsMobile',
            'model'      => Payment::class,
            'primaryKey' => 'paymentsID',
            'pageSize'   => 1000,
            'responseKey'   => 'body',
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],
            'fieldMap' => function (array $r) use ($syncService) {
                return [
                    'clientsID'                  => (int)($r['clientsID'] ?? 0),
                    'localizationsID'            => (int)($r['localizationsID'] ?? 0),
                    'usersID'                    => (int)($r['usersID'] ?? 0),
                    'payer_UsersID'              => (int)($r['payer_UsersID'] ?? 0),
                    'cashDesksIK'               => (string)($r['cashDesksIK'] ?? ''),
                    'recepcionist_UsersID'       => (int)($r['recepcionist_UsersID'] ?? 0),
                    'paymentMethodsDVID'         => (int)($r['paymentMethodsDVID'] ?? 0),
                    'paymentStatusesDVID'       => (int)($r['paymentStatusesDVID'] ?? 0),
                    'paymentDate'                => $syncService->validateDate($r['paymentDate'] ?? '', null),
                    'paymentAmount'              => (float)($r['paymentAmount'] ?? 0),
                    'cancelled'                  => (int)($r['cancelled'] ?? 0),
                    'whenInserted'               => $syncService->validateDate($r['whenInserted'] ?? '', now()),
                    'whoInserted_UsersID'        => (int)($r['whoInserted_UsersID'] ?? 0),
                    'whenUpdated'                => $syncService->validateDate($r['whenUpdated'] ?? '', now()),
                    'whoUpdated_UsersID'         => (int)($r['whoUpdated_UsersID'] ?? 0),
                    'referencerNumberTransfe'    => (string)($r['referencerNumberTransfe'] ?? ''),
                    'buyerNIP'                   => (string)($r['buyerNIP'] ?? ''),
                    'fiscalized'                 => (int)($r['fiscalized'] ?? 0),
                    'fiscalizedDate'             => $syncService->validateDate($r['fiscalizedDate'] ?? '', null),
                    'rejectionDate'              => $syncService->validateDate($r['rejectionDate'] ?? '', now()),
                    'reasonForRejection'         => (string)($r['reasonForRejection'] ?? ''),
                    'bulksIDS'                   => (int)($r['bulksIDS'] ?? 0),
                    'reduceFromWallet_UsersID'   => (int)($r['reduceFromWallet_UsersID'] ?? 0),
                    'transfer_paymentsID'        => (int)($r['transfer_paymentsID'] ?? 0),
                    'original_paymentsID'        => (int)($r['original_paymentsID'] ?? null),
                    'giftcardKey'                => (string)($r['giftcardKey'] ?? ''),
                ];
            },
        ]);
    }
}
