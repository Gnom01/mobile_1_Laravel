<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\ProductsDimension;
use App\Services\CrmSyncService;

class PullProductsDimensionsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService): void
    {
        $syncService->sync([
            'resource'        => 'productsdimensions',
            'endpoint'        => '/CrmToMobileSync/getProductsDimensionsMobile',
            'model'           => ProductsDimension::class,
            'primaryKey'      => 'productsdimensionsid',
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
