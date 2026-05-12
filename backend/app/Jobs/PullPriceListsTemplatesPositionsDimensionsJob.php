<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\PriceListsTemplatesPositionsDimension;
use App\Services\CrmSyncService;

class PullPriceListsTemplatesPositionsDimensionsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService): void
    {
        $syncService->sync([
            'resource'        => 'priceliststemplatespositionsdimensions',
            'endpoint'        => '/CrmToMobileSync/getPriceListsTemplatesPositionsDimensionsMobile',
            'model'           => PriceListsTemplatesPositionsDimension::class,
            'primaryKey'      => 'priceliststemplatespositionsdimensionsid',
            'pageSize'        => 1000,
            'responseKey'     => 'body',
            'whenUpdatedKey'  => 'whenupdated',
            'orderParam'      => 'whenupdated ASC',
            'fieldMap'        => fn(array $r): array => $r,
        ]);
    }
}
