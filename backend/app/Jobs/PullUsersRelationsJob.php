<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\UsersRelation;
use App\Services\CrmSyncService;

class PullUsersRelationsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService)
    {
        $syncService->sync([
            'resource'   => 'usersrelations',
            'endpoint'   => '/CrmToMobileSync/getUsersRelationMobile',
            'model'      => UsersRelation::class,
            'primaryKey'    => 'UsersRelationsID',
            'apiPrimaryKey' => 'usersRelationsID',
            'pageSize'   => 1000,
            'responseKey' => 'body',
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],
            'fieldMap' => function (array $r) use ($syncService) {
                return [
                    'Parent_UsersID'              => (int)($r['parent_UsersID'] ?? 0),
                    'UsersID'                     => (int)($r['usersID'] ?? 0),
                    'ParticipantRelationsDVID'    => (int)($r['participantRelationsDVID'] ?? 0),
                    'Description'                 => (string)($r['description'] ?? ''),
                    'DateFrom'                    => $syncService->validateDate($r['dateFrom'] ?? '', null),
                    'DateTo'                      => $syncService->validateDate($r['dateTo'] ?? '', null),
                    'Cancelled'                   => (int)($r['cancelled'] ?? 0),
                    'WhenInserted'                => $syncService->validateDate($r['whenInserted'] ?? now(), now()),
                    'WhoInserted_UsersID'         => (int)($r['whoInserted_UsersID'] ?? 0),
                    'WhenUpdated'                 => $syncService->validateDate($r['whenUpdated'] ?? now(), now()),
                    'WhoUpdated_UsersID'          => (int)($r['whoUpdated_UsersID'] ?? 0),
                    'LocalizationsID'             => (int)($r['localizationsID'] ?? 0),
                    'Status'                      => (int)($r['status'] ?? 0),
                ];
            },
        ]);
    }
}
