<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\ProductsPaymentInstallment;
use App\Services\CrmSyncService;

class PullProductsPaymentInstallmentsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService): void
    {
        $syncService->sync([
            'resource'        => 'productspaymentinstallments',
            'endpoint'        => '/CrmToMobileSync/getProductsPaymentInstallmentsMobile',
            'model'           => ProductsPaymentInstallment::class,
            'primaryKey'      => 'productspaymentinstallmentsid',
            'pageSize'        => 1000,
            'responseKey'     => 'body',
            'whenUpdatedKey'  => 'whenupdated',
            'orderParam'      => 'whenupdated ASC',
            'fieldMap'        => fn(array $r): array => $r,
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],
        ]);
    }
}
