<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\XSchedule;
use App\Services\CrmSyncService;

class PullXSchedulesJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService): void
    {
        $syncService->sync([
            'resource'        => 'xschedules',
            'endpoint'        => '/CrmToMobileSync/getXSchedulesMobile',
            'model'           => XSchedule::class,
            'primaryKey'      => 'id',
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
