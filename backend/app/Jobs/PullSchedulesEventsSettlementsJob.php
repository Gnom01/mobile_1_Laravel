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
            'primaryKey'      => 'scheduleseventssettlementsid',
            'pageSize'        => 100,
            'responseKey'     => 'body',
            'whenUpdatedKey'  => 'whenupdated',
            'orderParam'      => 'whenupdated ASC',
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],
            'fieldMap' => function (array $r): array {
                // Exclude generated/computed columns — MySQL rejects explicit values for them.
                // The CRM returns lowercase keys, so unset both cases to be safe.
                unset(
                    $r['startDateTime'],    $r['startdatetime'],
                    $r['endDateTime'],      $r['enddatetime'],
                    $r['durationInMinutes'],$r['durationinminutes']
                );
                return $r;
            },
        ]);
    }
}