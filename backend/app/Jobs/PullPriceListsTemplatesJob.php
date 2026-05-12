<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\PriceListsTemplate;
use App\Services\CrmSyncService;

class PullPriceListsTemplatesJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService): void
    {
        $syncService->sync([
            'resource'        => 'priceliststemplates',
            'endpoint'        => '/CrmToMobileSync/getPriceListsTemplatesMobile',
            'model'           => PriceListsTemplate::class,
            'primaryKey'      => 'priceliststemplatesid',
            'pageSize'        => 1000,
            'responseKey'     => 'body',
            'whenUpdatedKey'  => 'whenupdated',
            'orderParam'      => 'whenupdated ASC',
            'fieldMap'        => fn(array $r): array => $r,
        ]);
    }
}
