<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmUser;
use App\Models\UsersRelation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractController extends Controller
{
    private const MONTH_NAMES_PL = [
        1  => 'Styczeń',
        2  => 'Luty',
        3  => 'Marzec',
        4  => 'Kwiecień',
        5  => 'Maj',
        6  => 'Czerwiec',
        7  => 'Lipiec',
        8  => 'Sierpień',
        9  => 'Wrzesień',
        10 => 'Październik',
        11 => 'Listopad',
        12 => 'Grudzień',
    ];

    /**
     * Get contracts for a user (and their related users).
     *
     * GET /api/contracts/{parentGuid}
     */
    public function getContracts(Request $request, string $parentGuid)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['success' => false, 'error' => 'UNAUTHORIZED'], 401);
        }

        // Resolve target user by GUID
        $targetUser = CrmUser::where('guid', $parentGuid)
            ->where('Cancelled', 0)
            ->first();

        if (!$targetUser) {
            return response()->json(['success' => false, 'error' => 'USER_NOT_FOUND'], 404);
        }

        // Access control
        $isSelf = (int) $targetUser->UsersID === (int) $authUser->UsersID;

        $relatedIds = UsersRelation::where('Parent_UsersID', $authUser->UsersID)
            ->where('Cancelled', 0)
            ->pluck('UsersID')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $isRelated = in_array((int) $targetUser->UsersID, $relatedIds);

        if (!$isSelf && !$isRelated) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        $usersID = (int) $targetUser->UsersID;

        // Build full list: target user + their family members
        $familyIds = UsersRelation::where('Parent_UsersID', $usersID)
            ->where('Cancelled', 0)
            ->pluck('UsersID')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $allUserIds  = array_values(array_unique(array_merge([$usersID], $familyIds)));
        $placeholders = implode(',', array_fill(0, count($allUserIds), '?'));

        // Fetch contracts with joined data
        $contracts = DB::select("
            SELECT
                c.contractsID,
                c.parent_ContractsID,
                c.sellingParent_ContractsID,
                c.usersID,
                c.usersID          AS contracts_UsersID,
                c.payer_UsersID,
                c.contractSygnature,
                c.contractsTypesDVID,
                c.contractStatusesDVID,
                d_status.Name      AS contractStatusName,
                c.contractsPatternsID,
                c.contractPatternName,
                c.productsID,
                c.productName,
                c.paymentName,
                c.courseHeadingName,
                c.coursesHeadingsID,
                c.contracConclusionDate,
                c.contractPeriodFrom,
                c.contractPeriodTo,
                c.contractPeriodFrom AS contractPeriodFromOld,
                c.contractAmount,
                c.entryFee,
                c.localizationsID,
                c.localizationsID  AS groupLocation,
                l.localizationName AS localizationsName,
                c.durationInMinutesDVID,
                c.cancelled,
                c.note,
                c.expirationDate,
                c.userFirstName,
                c.userLastName,
                CONCAT(c.userLastName, ' ', c.userFirstName) AS fullNameEDS,
                CONCAT(c.userLastName, ' ', c.userFirstName) AS contractForUser,
                c.userAddress,
                c.userPostCode,
                c.userCity,
                c.userIdentityNumber,
                c.userPESEL,
                c.userPhone,
                c.userEmail,
                c.payerFirstName,
                c.payerLastName,
                CONCAT(c.payerLastName, ' ', c.payerFirstName) AS payerName,
                c.payerAddress,
                c.payerPostCode,
                c.payerCity,
                c.payerIdentityNumber,
                c.payerPESEL,
                c.payerPhone,
                c.payerEmail,
                u.DateOfBirdth     AS dateOfBirdth,
                pr.PaymentTypesDVID   AS paymentTypesDVID,
                pr.ProductsLevel2DVID AS productsLevel2DVID,
                pr.ProductsLevel3DVID AS productsLevel3DVID,
                pr.DurationInMinutes  AS durationMin,
                c.whenUpdated,
                NULL               AS usersPaymentsSchedulesID
            FROM contracts c
            LEFT JOIN localizations l
                ON l.LocalizationsID = c.localizationsID
                AND l.Cancelled = 0
            LEFT JOIN dictionaries d_status
                ON d_status.DictionaryName = 'ContractStatuses'
                AND d_status.valueID = c.contractStatusesDVID
                AND d_status.Cancelled = 0
            LEFT JOIN users u
                ON u.UsersID = c.usersID
                AND u.Cancelled = 0
            LEFT JOIN products pr
                ON pr.ProductsID = c.productsID
                AND pr.Cancelled = 0
            WHERE c.cancelled = 0
                AND c.usersID IN ($placeholders)
            ORDER BY c.contractsID DESC
        ", $allUserIds);

        // For each contract, fetch installments from userspaymentsschedules
        foreach ($contracts as $index => $contract) {
            $schedules = DB::select("
                SELECT
                    ups.positionName,
                    ups.paymentDate,
                    ups.instalmentNumber,
                    ups.leftToPaid AS paymentAmount,
                    MONTH(ups.paymentDate) AS month_no
                FROM userspaymentsschedules ups
                WHERE ups.contractsID = ?
                    AND ups.cancelled = 0
                ORDER BY ups.instalmentNumber ASC
            ", [$contract->contractsID]);

            $installmentBody = array_map(function ($row) {
                $monthNo = (int) $row->month_no;
                return [
                    'positionName'              => $row->positionName,
                    'paymentDate'               => $row->paymentDate,
                    'instalmentNumber'          => (int) $row->instalmentNumber,
                    'paymentAmount'             => (float) $row->paymentAmount,
                    'month_no'                  => $monthNo,
                    'monthName'                 => self::MONTH_NAMES_PL[$monthNo] ?? '',
                    'fullNameEDS'               => '',
                    'paymentTypesDVID'          => 0,
                    'contractsID'               => 0,
                    'parent_ContractsID'        => 0,
                    'sellingParent_ContractsID' => 0,
                    'contracts_UsersID'         => 0,
                    'contractPeriodFrom'        => '',
                    'contractSygnature'         => '',
                    'productsID'                => 0,
                    'entryFee'                  => 0,
                    'contractsTypesDVID'        => 0,
                    'contracConclusionDate'     => '',
                    'contractAmount'            => 0,
                    'contractStatusesDVID'      => 0,
                    'contractStatusName'        => '',
                    'contractsPatternsID'       => 0,
                    'courseHeadingName'         => '',
                    'coursesHeadingsID'         => 0,
                    'contractPatternName'       => '',
                    'productName'               => '',
                    'paymentName'               => '',
                    'contractPeriodTo'          => '',
                    'usersID'                   => 0,
                    'dateOfBirdth'              => '',
                    'userPhone'                 => '',
                    'installments'              => '',
                    'userEmail'                 => '',
                    'durationInMinutesDVID'     => 0,
                    'userFirstName'             => '',
                    'userLastName'              => '',
                    'userAddress'               => '',
                    'userPostCode'              => '',
                    'userCity'                  => '',
                    'userIdentityNumber'        => '',
                    'userPESEL'                 => '',
                    'payer_UsersID'             => 0,
                    'payerFirstName'            => '',
                    'payerLastName'             => '',
                    'payerPostCode'             => '',
                    'payerAddress'              => '',
                    'payerIdentityNumber'       => '',
                    'payerCity'                 => '',
                    'payerPhone'                => '',
                    'payerEmail'                => '',
                    'payerPESEL'                => '',
                    'localizationsID'           => 0,
                    'contractPeriodFromOld'     => '',
                    'localizationsName'         => '',
                    'productsLevel2DVID'        => 0,
                    'productsLevel3DVID'        => 0,
                    'note'                      => '',
                    'contractHeader'            => '',
                    'groupLocation'             => 0,
                    'productsLevel2'            => 0,
                    'productsLevel3'            => 0,
                    'durationMin'               => '',
                    'frequency'                 => '',
                    'debt'                      => 0,
                    'contractForUser'           => '',
                    'cancelled'                 => 0,
                    'payerName'                 => '',
                    'whenUpdated'               => '',
                ];
            }, $schedules);

            $contracts[$index]->installments = [
                'status'      => '200',
                'message'     => '',
                'body'        => $installmentBody,
                'recordCount' => count($installmentBody),
            ];
        }

        return response()->json([
            'success' => true,
            'body'    => $contracts,
        ]);
    }
}