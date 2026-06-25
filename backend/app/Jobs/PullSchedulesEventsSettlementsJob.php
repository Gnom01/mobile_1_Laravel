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
            'pageSize'        => 1000,
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

                // Derive eventdate from integer fields if CRM returned an invalid/empty date.
                if (empty($r['eventdate']) || $r['eventdate'] === '0000-00-00') {
                    $intDate = (int) ($r['inteventdate'] ?? 0);
                    if ($intDate === 0) {
                        $intDate = (int) ($r['masterinteventdate'] ?? 0);
                    }
                    if ($intDate > 0) {
                        $s = (string) $intDate;
                        $r['eventdate'] = substr($s, 0, 4) . '-' . substr($s, 4, 2) . '-' . substr($s, 6, 2);
                    }
                }

                return $r;
            },
            'skipIf' => function (array $r): bool {
                // Skip records where we cannot determine a valid eventdate.
                $eventdate    = $r['eventdate'] ?? '';
                $inteventdate = (int) ($r['inteventdate']       ?? 0);
                $masterInt    = (int) ($r['masterinteventdate'] ?? 0);

                $hasDate = !empty($eventdate) && $eventdate !== '0000-00-00';
                $hasInt  = $inteventdate > 0 || $masterInt > 0;

                return !$hasDate && !$hasInt;
            },
        ]);
    }
}