<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Contract;
use App\Services\CrmSyncService;

class PullContractsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService)
    {
        $syncService->sync([
            'resource'   => 'contracts',
            'endpoint'   => '/CrmToMobileSync/getContractsMobile',
            'model'      => Contract::class,
            'primaryKey' => 'contractsID',
            'pageSize'   => 1000,
            'responseKey'   => 'body',
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],
            'fieldMap' => function (array $r) use ($syncService) {
                return [
                    'parent_ContractsID'                => (int)($r['parent_ContractsID'] ?? 0),
                    'sellingParent_ContractsID'         => (int)($r['sellingParent_ContractsID'] ?? 0),
                    'contractsTypesDVID'               => (int)($r['contractsTypesDVID'] ?? 0),
                    'contractSygnature'                => (string)($r['contractSygnature'] ?? ''),
                    'contractLocalizationOrdinalNumber' => (int)($r['contractLocalizationOrdinalNumber'] ?? 0),
                    'contractStatusesDVID'             => (int)($r['contractStatusesDVID'] ?? 0),
                    'contractsPatternsID'              => (int)($r['contractsPatternsID'] ?? 0),
                    'contractPatternName'              => (string)($r['contractPatternName'] ?? ''),
                    'productsID'                       => (int)($r['productsID'] ?? 0),
                    'productName'                      => (string)($r['productName'] ?? ''),
                    'packagesID'                       => (int)($r['packagesID'] ?? 0),
                    'packageName'                      => (string)($r['packageName'] ?? ''),
                    'coursesHeadingsID'                => (int)($r['coursesHeadingsID'] ?? 0),
                    'courseHeadingName'                => (string)($r['courseHeadingName'] ?? ''),
                    'contracConclusionDate'            => $syncService->validateDate($r['contracConclusionDate'] ?? '', null),
                    'contractPeriodFrom'               => $syncService->validateDate($r['contractPeriodFrom'] ?? '', null),
                    'contractPeriodTo'                 => $syncService->validateDate($r['contractPeriodTo'] ?? '', null),
                    'contractAmount'                   => (float)($r['contractAmount'] ?? 0),
                    'contractAmountText'               => (string)($r['contractAmountText'] ?? ''),
                    'usersID'                          => (int)($r['usersID'] ?? 0),
                    'userFirstName'                    => (string)($r['userFirstName'] ?? ''),
                    'userLastName'                     => (string)($r['userLastName'] ?? ''),
                    'userAddress'                      => (string)($r['userAddress'] ?? ''),
                    'userPostCode'                     => (string)($r['userPostCode'] ?? ''),
                    'userCity'                         => (string)($r['userCity'] ?? ''),
                    'userIdentityNumber'               => (string)($r['userIdentityNumber'] ?? ''),
                    'userPESEL'                        => (string)($r['userPESEL'] ?? ''),
                    'payer_UsersID'                    => (int)($r['payer_UsersID'] ?? 0),
                    'payerFirstName'                   => (string)($r['payerFirstName'] ?? ''),
                    'payerLastName'                    => (string)($r['payerLastName'] ?? ''),
                    'payerAddress'                     => (string)($r['payerAddress'] ?? ''),
                    'payerPostCode'                    => (string)($r['payerPostCode'] ?? ''),
                    'payerCity'                        => (string)($r['payerCity'] ?? ''),
                    'payerIdentityNumber'              => (string)($r['payerIdentityNumber'] ?? ''),
                    'payerPESEL'                       => (string)($r['payerPESEL'] ?? ''),
                    'localizationsID'                  => (int)($r['localizationsID'] ?? 0),
                    'cancelled'                        => (int)($r['cancelled'] ?? 0),
                    'whenInserted'                     => $syncService->validateDate($r['whenInserted'] ?? '', now()),
                    'whoInserted_UsersID'              => (int)($r['whoInserted_UsersID'] ?? 0),
                    'whenUpdated'                      => $syncService->validateDate($r['whenUpdated'] ?? '', now()),
                    'whoUpdated_UsersID'               => (int)($r['whoUpdated_UsersID'] ?? 0),
                    'durationInMinutesDVID'            => (int)($r['durationInMinutesDVID'] ?? 0),
                    'expirationDate'                   => $syncService->validateDate($r['expirationDate'] ?? '', now()),
                    'courseLength'                     => (string)($r['courseLength'] ?? ''),
                    'courseLengthWeek'                 => (string)($r['courseLengthWeek'] ?? ''),
                    'installmentValueZero'             => (float)($r['installmentValueZero'] ?? 0.00),
                    'entryFee'                         => (float)($r['entryFee'] ?? 0.00),
                    'sumOfInitialCharges'              => (float)($r['sumOfInitialCharges'] ?? 0.00),
                    'paymentName'                      => (string)($r['paymentName'] ?? ''),
                    'numberOfFullInstallments'         => (int)($r['numberOfFullInstallments'] ?? 0),
                    'monthlyInstallment'               => (float)($r['monthlyInstallment'] ?? 0.00),
                    'userPhone'                        => (string)($r['userPhone'] ?? ''),
                    'userEmail'                        => (string)($r['userEmail'] ?? ''),
                    'payerPhone'                       => (string)($r['payerPhone'] ?? ''),
                    'payerEmail'                       => (string)($r['payerEmail'] ?? ''),
                    'contractsBulksIDS'                => (int)($r['contractsBulksIDS'] ?? 0),
                    'note'                             => (string)($r['note'] ?? ''),
                ];
            },
        ]);
    }
}
