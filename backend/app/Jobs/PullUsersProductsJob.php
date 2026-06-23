<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\UsersProduct;
use App\Services\CrmSyncService;

class PullUsersProductsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService): void
    {
        $syncService->sync([
            'resource'       => 'usersproducts',
            'endpoint'       => '/CrmToMobileSync/getUsersProductsMobile',
            'model'          => UsersProduct::class,
            'primaryKey'     => 'usersproductsid',
            'pageSize'       => 1000,
            'responseKey'    => 'body',
            'whenUpdatedKey' => 'whenupdated',
            'orderParam'     => 'whenupdated ASC',
            'extraParams'    => [
                'current_LocalizationsID' => '0',
            ],
            'fieldMap'       => function (array $r): array {
                // productsLevels is a stored generated column — MySQL rejects explicit inserts
                unset($r['productsLevels'], $r['productslevels']);

                foreach (['vatratesik', 'paymentmethodsdvidlist', 'promotrionsidlist'] as $column) {
                    if (($r[$column] ?? null) === null) {
                        $r[$column] = '';
                    }
                }

                return $r;
            },
        ]);
    }
}
