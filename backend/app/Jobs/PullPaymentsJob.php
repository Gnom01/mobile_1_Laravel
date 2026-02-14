<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\UsersPaymentsSchedule;
use App\Services\CrmSyncService;

class PullPaymentsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService)
    {
        $syncService->sync([
            'resource'   => 'payments',
            'endpoint'   => '/CrmToMobileSync/getUserspaymentsschedulesMobile',
            'model'      => UsersPaymentsSchedule::class,
            'primaryKey' => 'usersPaymentsSchedulesID',
            'pageSize'   => 1000,
            'responseKey' => 'body',
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],
            'fieldMap' => function (array $r) use ($syncService) {
                $paymentDate = $syncService->validateDate($r['paymentDate'] ?? '', null);
                if (empty($paymentDate)) {
                    \Illuminate\Support\Facades\Log::warning('[SYNC:payments] empty paymentDate', [
                        'usersPaymentsSchedulesID' => (int)($r['usersPaymentsSchedulesID'] ?? 0),
                        'usersID' => (int)($r['usersID'] ?? 0)
                    ]);
                }

                return [
                    'usersID'                   => (int)($r['usersID'] ?? 0),
                    'contractsID'               => (int)($r['contractsID'] ?? 0),
                    'productsID'                => (int)($r['productsID'] ?? 0),
                    'coursesHeadingsID'         => (int)($r['coursesHeadingsID'] ?? 0),
                    'instalmentNumber'          => (int)($r['instalmentNumber'] ?? 0),
                    'contractInstalmentNumber'  => (int)($r['contractInstalmentNumber'] ?? 0),
                    'voidInstalment'            => (int)($r['voidInstalment'] ?? 0),
                    'positionName'              => (string)($r['positionName'] ?? $r['productName'] ?? ''),
                    'productAvailableFromDate'  => (string)($r['productAvailableFromDate'] ?? ''),
                    'productAvailableToDate'    => (string)($r['productAvailableToDate'] ?? ''),
                    'lessonsAreCounted'         => (int)($r['lessonsAreCounted'] ?? 0),
                    'lessonsRemainingForUse'    => (int)($r['lessonsRemainingForUse'] ?? 0),
                    'paymentDate'               => $paymentDate,
                    'paymentAmount'             => (float)($r['paymentAmount'] ?? 0),
                    'paymentStatusesDVID'       => (int)($r['paymentStatusesDVID'] ?? 1),
                    'paymentMethodDVIDList'     => (string)($r['paymentMethodDVIDList'] ?? '0'),
                    'amountPaid'                => (float)($r['amountPaid'] ?? 0),
                    'amountTransferred'         => (float)($r['amountTransferred'] ?? 0),
                    'amountCorrected'           => (float)($r['amountCorrected'] ?? 0),
                    'comments'                  => (string)($r['comments'] ?? ''),
                    'localizationsID'           => (int)($r['localizationsID'] ?? 0),
                    'cancelled'                 => (int)($r['cancelled'] ?? 0),
                    'whenInserted'              => $syncService->validateDate($r['whenInserted'] ?? '', now()),
                    'whoInserted_UsersID'       => (int)($r['whoInserted_UsersID'] ?? 0),
                    'whenUpdated'               => $syncService->validateDate($r['whenUpdated'] ?? '', now()),
                    'whoUpdated_UsersID'        => (int)($r['whoUpdated_UsersID'] ?? 0),
                    'price'                     => (float)($r['price'] ?? 0),
                    'usersProductsID'           => (int)($r['usersProductsID'] ?? 0),
                    'lastPaymentDate'           => $syncService->validateDate($r['lastPaymentDate'] ?? '', null),
                    'processesDVID'             => (int)($r['processesDVID'] ?? 0),
                    'payer_UsersID'             => (int)($r['payer_UsersID'] ?? 0),
                    'paymentMethodDVID'         => (string)($r['paymentMethodDVID'] ?? ''),
                ];
            },
        ]);
    }
}
