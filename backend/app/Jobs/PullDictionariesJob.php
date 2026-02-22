<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Dictionary;
use App\Services\CrmSyncService;

class PullDictionariesJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService)
    {
        $syncService->sync([
            'resource'   => 'dictionaries',
            'endpoint'   => '/CrmToMobileSync/getDictionariesMobile',
            'model'      => Dictionary::class,
            'primaryKey' => 'DictionariesID',
            'pageSize'   => 1000,
            'responseKey'   => 'body',
            'fieldMap' => function (array $r) use ($syncService) {
                return [
                    'Parent_DictionariesID' => (int)($r['parent_DictionariesID'] ?? 0),
                    'Parent_DictionaryName' => (string)($r['parent_DictionaryName'] ?? ''),
                    'Parent_ValueID'        => (int)($r['parent_ValueID'] ?? 0),
                    'DictionaryName'        => (string)($r['dictionaryName'] ?? ''),
                    'Name'                  => (string)($r['name'] ?? ''),
                    'ValueID'               => (int)($r['valueID'] ?? 0),
                    'ValueText'             => (string)($r['valueText'] ?? ''),
                    'OrderPosition'         => (int)($r['orderPosition'] ?? 0),
                    'Description'           => (string)($r['description'] ?? ''),
                    'Editable'              => (int)($r['editable'] ?? 1),
                    'Cancelled'             => (int)($r['cancelled'] ?? 0),
                    'WhenInserted'          => $syncService->validateDate($r['whenInserted'] ?? '', now()),
                    'WhoInserted_UsersID'   => (int)($r['whoInserted_UsersID'] ?? 0),
                    'WhenUpdated'           => $syncService->validateDate($r['whenUpdated'] ?? '', now()),
                    'WhoUpdated_UsersID'    => (int)($r['whoUpdated_UsersID'] ?? 0),
                    'ItemColor'             => (string)($r['itemColor'] ?? ''),
                    'Hidden'                => (int)($r['hidden'] ?? 0),
                ];
            },
        ]);
    }
}
