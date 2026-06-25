<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Day;
use App\Services\CrmSyncService;

class PullDaysJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService): void
    {
        $syncService->sync([
            'resource'        => 'days',
            'endpoint'        => '/CrmToMobileSync/getDaysMobile',
            'model'           => Day::class,
            'primaryKey'      => 'intdate',
            'pageSize'        => 100,
            'responseKey'     => 'body',
            'whenUpdatedKey'  => 'whenupdated',
            'orderParam'      => 'intdate ASC',
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],
            'fieldMap'        => fn(array $r): array => $r,
        ]);
    }
}
