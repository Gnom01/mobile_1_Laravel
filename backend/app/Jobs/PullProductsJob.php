<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Product;
use App\Services\CrmSyncService;

class PullProductsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService)
    {
        $syncService->sync([
            'resource'   => 'products',
            'endpoint'   => '/CrmToMobileSync/getProductsMobile',
            'model'      => Product::class,
            'primaryKey' => 'ProductsID',
            'pageSize'   => 1000,
            'responseKey' => 'body',
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],

            'fieldMap' => function (array $r) use ($syncService) {
                $int = static fn (array $data, string $key): int
                    => array_key_exists($key, $data) ? (int) $data[$key] : 0;
                $str = static fn (array $data, string $key): string
                    => array_key_exists($key, $data) ? (string) $data[$key] : '';
                $intAny = static function (array $data, array $keys): int {
                    foreach ($keys as $key) {
                        if (array_key_exists($key, $data)) {
                            return (int) $data[$key];
                        }
                    }
                    return 0;
                };
                $strAny = static function (array $data, array $keys): string {
                    foreach ($keys as $key) {
                        if (array_key_exists($key, $data)) {
                            return (string) $data[$key];
                        }
                    }
                    return '';
                };
                $intNullable = static function (array $data, array $keys): ?int {
                    foreach ($keys as $key) {
                        if (array_key_exists($key, $data)) {
                            return $data[$key] !== null ? (int) $data[$key] : null;
                        }
                    }
                    return null;
                };

                return [
                    'ProductsLevel1DVID'              => $intAny($r, ['ProductsLevel1DVID', 'productsLevel1DVID']),
                    'ProductsLevel2DVID'              => $intAny($r, ['ProductsLevel2DVID', 'productsLevel2DVID']),
                    'ProductsLevel3DVID'              => $intAny($r, ['ProductsLevel3DVID', 'productsLevel3DVID']),
                    'DimensionsPatternsID'            => $intAny($r, ['DimensionsPatternsID', 'dimensionsPatternsID']),
                    'CoursesHeadingsID'               => $intAny($r, ['CoursesHeadingsID', 'coursesHeadingsID']),
                    'PriceListsTemplatesID'           => $intAny($r, ['PriceListsTemplatesID', 'priceListsTemplatesID']),
                    'PriceListsTemplatesPositionsID'  => $intAny($r, ['PriceListsTemplatesPositionsID', 'priceListsTemplatesPositionsID']),
                    'ProductName'                     => $strAny($r, ['ProductName', 'productName']),
                    'ContractsPatternsID'             => $intAny($r, ['ContractsPatternsID', 'contractsPatternsID']),
                    'PricelistPositionsTypesDVID'     => $intAny($r, ['PricelistPositionsTypesDVID', 'pricelistPositionsTypesDVID']),
                    'PeriodsOfValidityDVID'           => $intAny($r, ['PeriodsOfValidityDVID', 'periodsOfValidityDVID']),
                    'NumberOfPeriods'                 => $strAny($r, ['NumberOfPeriods', 'numberOfPeriods']),
                    'UnitsOfAccountDVID'              => $strAny($r, ['UnitsOfAccountDVID', 'unitsOfAccountDVID']),
                    'NumberOfUnitsAccount'            => $strAny($r, ['NumberOfUnitsAccount', 'numberOfUnitsAccount']),
                    'StartingDate'                    => $syncService->validateDate($strAny($r, ['StartingDate', 'startingDate']), now()),
                    'ClosingDate'                     => $syncService->validateDate($strAny($r, ['ClosingDate', 'closingDate']), now()),
                    'ExpirationDate'                  => $syncService->validateDate($strAny($r, ['ExpirationDate', 'expirationDate']), now()),
                    'AccountNumber'                   => $strAny($r, ['AccountNumber', 'accountNumber']),
                    'TemplateValueChanged'            => $intAny($r, ['TemplateValueChanged', 'templateValueChanged']),
                    'UnitPrice'                       => (float) ($r['UnitPrice'] ?? $r['unitPrice'] ?? 0),
                    'Price'                           => (float) ($r['Price'] ?? $r['price'] ?? 0),
                    'VatRatesIK'                      => $strAny($r, ['VatRatesIK', 'vatRatesIK']),
                    'Description'                     => $strAny($r, ['Description', 'description']),
                    'Cancelled'                       => $intAny($r, ['Cancelled', 'cancelled']),
                    'LocalizationsID'                 => $intAny($r, ['LocalizationsID', 'localizationsID']),
                    'NumberOfLessons'                 => $intAny($r, ['NumberOfLessons', 'numberOfLessons']),
                    'amountCoursesFrom'               => $intAny($r, ['amountCoursesFrom']),
                    'amountCoursesTo'                 => $intAny($r, ['amountCoursesTo']),
                    'PaymentTypesDVID'                => $intAny($r, ['PaymentTypesDVID', 'paymentTypesDVID']),
                    'PaymentMethodsDVID'              => $strAny($r, ['PaymentMethodsDVID', 'paymentMethodsDVID']),
                    'UsersGroupsDVID'                 => $intAny($r, ['UsersGroupsDVID', 'usersGroupsDVID']),
                    'TemplateValuesChanged'           => $intAny($r, ['TemplateValuesChanged', 'templateValuesChanged']),
                    'ProductsTypes'                   => $strAny($r, ['ProductsTypes', 'productsTypes']),
                    'ProductsUnitDVID'                => $intNullable($r, ['ProductsUnitDVID', 'productsUnitDVID']),
                    'ProductsNameReceipt'             => $strAny($r, ['ProductsNameReceipt', 'productsNameReceipt']),
                    'DurationInMinutes'               => $intNullable($r, ['DurationInMinutes', 'durationInMinutes']),
                    'WhenInserted'                    => $syncService->validateDate($strAny($r, ['WhenInserted', 'whenInserted']), now()),
                    'WhoInserted_UsersID'             => $intAny($r, ['WhoInserted_UsersID', 'whoInserted_UsersID']),
                    'WhenUpdated'                     => $syncService->validateDate($strAny($r, ['WhenUpdated', 'whenUpdated']), now()),
                    'WhoUpdated_UsersID'              => $intAny($r, ['WhoUpdated_UsersID', 'whoUpdated_UsersID']),
                    'metaID'                          => $strAny($r, ['metaID']),
                    'hidden'                          => $intNullable($r, ['hidden']),
                    'minOfLessons'                    => $intAny($r, ['minOfLessons']),
                    'isDeposit'                       => $intNullable($r, ['isDeposit']),
                ];
            },
        ]);
    }
}
