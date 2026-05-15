<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\SchedulesEventSettlement;
use App\Services\CrmSyncService;

class PullSchedulesEventsSettlementsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService): void
    {
        $syncService->sync([
            'resource'        => 'scheduleseventssettlements',
            'endpoint'        => '/CrmToMobileSync/getSchedulesEventsSettlementsMobile',
            'model'           => SchedulesEventSettlement::class,
            'primaryKey'      => 'schedulesEventsSettlementsID',
            'pageSize'        => 1000,
            'responseKey'     => 'body',
            'whenUpdatedKey'  => 'whenupdated',
            'orderParam'      => 'whenupdated ASC',
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],
            'fieldMap' => function (array $r): array {
                // Exclude generated/computed columns — MySQL rejects explicit values for them
                unset($r['startDateTime'], $r['endDateTime'], $r['durationInMinutes']);
                return $r;
            },
        ]);
    }
}
