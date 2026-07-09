<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\ClassRoom;
use App\Services\CrmSyncService;

class PullClassRoomsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService): void
    {
        $syncService->sync([
            'resource'        => 'classrooms',
            'endpoint'        => '/CrmToMobileSync/getClassRoomsMobile',
            'model'           => ClassRoom::class,
            'primaryKey'      => 'classRoomsID',
            'pageSize'        => 1000,
            'responseKey'     => 'body',
            'whenUpdatedKey'  => 'whenUpdated',
            'orderParam'      => 'whenUpdated ASC',
            'fieldMap'        => fn (array $r): array => $r,
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],
        ]);
    }
}
