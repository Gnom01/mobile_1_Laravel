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
            'extraParams' => [
                'current_LocalizationsID' => 0,
            ],
            'fieldMap' => function (array $r) use ($syncService) {
                $mapped = [
                    'Parent_DictionariesID' => ($r['Parent_DictionariesID'] ?? $r['parent_dictionariesid'] ?? 0),
                    'Parent_DictionaryName' => ($r["Parent_DictionaryName"] ?? ''),
                    'Parent_ValueID'        => ($r["Parent_ValueID"] ?? 0),
                    'DictionaryName'        => ($r['DictionaryName'] ?? ''),
                    'Name'                  => ($r['Name'] ?? ''),
                    'ValueID'               => ($r['ValueID'] ?? 0),
                    'ValueText'             => ($r['ValueText'] ?? ''),
                    'OrderPosition'         => ($r['OrderPosition'] ?? 0),
                    'Description'           => ($r['Description'] ?? ''),
                    'Editable'              => ($r['Editable'] ?? 1),
                    'Cancelled'             => ($r['Cancelled'] ?? 0),
                    'WhenInserted'          => $syncService->validateDate($r['WhenInserted'] ?? '', now()),
                    'WhoInserted_UsersID'   => ($r['WhoInserted_UsersID'] ?? 0),
                    'WhenUpdated'           => $syncService->validateDate($r['WhenUpdated'] ?? '', now()),
                    'WhoUpdated_UsersID'    => ($r['WhoUpdated_UsersID'] ?? 0),
                    'ItemColor'             => ($r['ItemColor'] ?? ''),
                    'Hidden'                => ($r['Hidden'] ?? 0),
                ];
 

                return $mapped;
            },
        ]);
    }
}
