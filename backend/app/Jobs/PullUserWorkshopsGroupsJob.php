<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\UserWorkshopsGroup;
use App\Services\CrmSyncService;

class PullUserWorkshopsGroupsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService): void
    {
        $syncService->sync([
            'resource'       => 'userworkshopsgroups',
            'endpoint'       => '/CrmToMobileSync/getUserWorkshopsGroupsMobile',
            'model'          => UserWorkshopsGroup::class,
            'primaryKey'     => 'userworkshopsgroupsid',
            'pageSize'       => 1000,
            'responseKey'    => 'body',
            'whenUpdatedKey' => 'whenupdated',
            'orderParam'     => 'whenupdated ASC',
            'extraParams'    => [
                'current_LocalizationsID' => '0',
            ],
            'fieldMap'       => fn(array $r): array => $r,
        ]);
    }
}
