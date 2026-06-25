<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmUser;
use App\Models\UsersRelation;
use App\Models\UsersPaymentsSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function getSchedule(Request $request)
    {
        $authUser = $request->user();

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'error' => 'UNAUTHORIZED',
            ], 401);
        }

        $usersID = $authUser->UsersID;

        // Fetch family members
        $familyIds = UsersRelation::where('Parent_UsersID', $usersID)
            ->where('Cancelled', 0)
            ->pluck('UsersID')
            ->toArray();

        // Add the user themselves to the list
        $allUserIds = array_merge([$usersID], $familyIds);

        // Fetch payments grouped by localization
        $payments = UsersPaymentsSchedule::query()
            ->select(
                'userspaymentsschedules.usersPaymentsSchedulesID',
                'u.fullName as UserFullName',
                'userspaymentsschedules.positionName as productName',
                'userspaymentsschedules.productAvailableFromDate',
                'userspaymentsschedules.productAvailableToDate',
                'userspaymentsschedules.lastPaymentDate',
                'userspaymentsschedules.usersID',
                'userspaymentsschedules.payer_UsersID',
                'userspaymentsschedules.paymentDate',
                'userspaymentsschedules.leftToPaid as paymentAmount',
                'userspaymentsschedules.paymentStatusesDVID',
                'userspaymentsschedules.localizationsID',
                'l.localizationName'
            )
            ->join('users as u', 'u.UsersID', '=', 'userspaymentsschedules.usersID')
            ->leftJoin('localizations as l', 'l.LocalizationsID', '=', 'userspaymentsschedules.localizationsID')
            ->whereIn('userspaymentsschedules.usersID', $allUserIds)
            ->where('userspaymentsschedules.Cancelled', 0)
            ->where('userspaymentsschedules.paymentStatusesDVID', 1)
            ->orderBy('userspaymentsschedules.orderValue')
            ->orderBy('userspaymentsschedules.paymentStatusesDVID')
            ->orderBy('userspaymentsschedules.paymentDate')
            ->get();

        // Group by localizationsID
        $groupedPayments = $payments->groupBy('localizationsID');

        return response()->json([
            'success' => true,
            'data' => $groupedPayments,
        ]);
    }

    /**
     * Get payment history for a user (and their related users).
     *
     * @param string $parentGuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentHistory(Request $request, $parentGuid)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'error' => 'UNAUTHORIZED',
            ], 401);
        }

        // Resolve target user by guid and validate relation to authenticated user.
        $targetUser = CrmUser::where('guid', $parentGuid)
            ->where('Cancelled', 0)
            ->first();

        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'error' => 'USER_NOT_FOUND',
            ], 404);
        }

        $isSelf = (int) $targetUser->UsersID === (int) $authUser->UsersID;

        $relatedIds = UsersRelation::where('Parent_UsersID', $authUser->UsersID)
            ->where('Cancelled', 0)
            ->pluck('UsersID')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $isRelated = in_array((int) $targetUser->UsersID, $relatedIds);

        if (!$isSelf && !$isRelated) {
            return response()->json([
                'success' => false,
                'error' => 'FORBIDDEN',
            ], 403);
        }

        $usersID = (int) $targetUser->UsersID;

        // Build full list: target user + their family members
        $familyIds = UsersRelation::where('Parent_UsersID', $usersID)
            ->where('Cancelled', 0)
            ->pluck('UsersID')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $allUserIds = array_values(array_unique(array_merge([$usersID], $familyIds)));

        // 2. Fetch Parent Payments
        $placeholders = implode(',', array_fill(0, count($allUserIds), '?'));

        $parentSql = "
            SELECT 
                p.usersID, p.paymentsID, p.paymentDate, p.cancelled, p.paymentMethodsDVID,
                d.Name AS paymentMethodsName, p.recepcionist_UsersID, u.fullName,
                ups.positionName,
                ups.usersPaymentsSchedulesID,
                pi.itemName,
                p.localizationsID, l.clientsID, l.localizationName,
                p.paymentAmount AS paymentItemAmount,
                d1.Name AS productPaymentsName,
                p.paymentStatusesDVID, d3.Name AS paymentStatusesName
            FROM payments p
            LEFT JOIN dictionaries d  ON d.valueID = p.paymentMethodsDVID AND d.DictionaryName = 'PaymentMethods' AND d.Cancelled = 0
            LEFT JOIN dictionaries d1 ON d1.DictionaryName = 'paymentTypes' AND d1.ValueID = p.paymentMethodsDVID AND d1.Cancelled = 0
            LEFT JOIN dictionaries d3 ON d3.DictionaryName = 'PaymentStatuses' AND d3.ValueID = p.paymentStatusesDVID AND d3.Cancelled = 0
            LEFT JOIN localizations l ON l.LocalizationsID = p.localizationsID AND l.Cancelled = 0
            LEFT JOIN paymentsitems pi ON pi.paymentsID = p.paymentsID AND pi.cancelled = 0
            LEFT JOIN users u         ON u.UsersID = p.usersID AND u.Cancelled = 0
            LEFT JOIN userspaymentsschedules ups ON ups.usersPaymentsSchedulesID = pi.usersPaymentsSchedulesID AND ups.cancelled = 0
            WHERE p.cancelled = 0
                AND p.paymentStatusesDVID IN (1,2,3,4)
                AND p.paymentMethodsDVID <> 4
                AND p.paymentAmount > 0
                AND (
                    p.usersID IN ($placeholders)
                    OR p.payer_UsersID IN ($placeholders)
                    OR pi.usersID IN ($placeholders)
                )
            ORDER BY p.paymentsID DESC
        ";

        $allPayment = DB::select($parentSql, array_merge($allUserIds, $allUserIds, $allUserIds));

        // Deduplicate – LEFT JOIN paymentsitems/userspaymentsschedules may produce
        // multiple rows per payment; keep first occurrence (which carries positionName).
        $allPayment = array_values(
            collect($allPayment)->unique('paymentsID')->all()
        );

        // 3. For each payment, fetch child items
        $childPlaceholders = implode(',', array_fill(0, count($allUserIds), '?'));

        $childSql = "
            SELECT
                pis.vatRatesIK,
                pr.productsLevel2DVID,
                pis.usersID,
                pis.paymentsItemsID,
                p.paymentsID,
                p.paymentDate,
                p.cancelled,
                p.paymentMethodsDVID,
                d.Name  AS paymentMethodsName,
                p.recepcionist_UsersID,
                u.fullName,
                pis.localizationsID,
                l.localizationName,
                pis.paymentItemAmount,
                pis.productsID,
                pr.productName,
                d2.Name AS productTapeName,
                d1.Name AS productPaymentsName,
                pr.paymentTypesDVID,
                pis.itemName,
                ups.paymentStatusesDVID,
                d3.Name AS paymentStatusesName,
                pis.contractsID
            FROM paymentsitems pis
            LEFT JOIN payments p
                ON p.paymentsID = pis.paymentsID
                AND p.cancelled = 0
            LEFT JOIN dictionaries d
                ON d.valueID = p.paymentMethodsDVID
                AND d.DictionaryName = 'PaymentMethods'
                AND d.Cancelled = 0
            LEFT JOIN users u
                ON p.usersID = u.UsersID
                AND u.Cancelled = 0
            LEFT JOIN localizations l
                ON l.LocalizationsID = pis.localizationsID
                AND l.Cancelled = 0
            LEFT JOIN products pr
                ON pr.ProductsID = pis.productsID
                AND pr.Cancelled = 0
            LEFT JOIN dictionaries d1
                ON d1.DictionaryName = 'paymentTypes'
                AND pr.paymentTypesDVID = d1.ValueID
                AND d1.Cancelled = 0
            LEFT JOIN dictionaries d2
                ON d2.DictionaryName = 'ProductsLevel2'
                AND pr.ProductsLevel2DVID = d2.ValueID
                AND d2.Cancelled = 0
            LEFT JOIN userspaymentsschedules ups
                ON ups.usersPaymentsSchedulesID = pis.usersPaymentsSchedulesID
                AND ups.cancelled = 0
            LEFT JOIN dictionaries d3
                ON d3.DictionaryName = 'PaymentStatuses'
                AND p.paymentStatusesDVID = d3.ValueID
                AND d3.Cancelled = 0
            WHERE p.paymentsID = ?
                AND pis.usersID IN ($childPlaceholders)
        ";

        foreach ($allPayment as $index => $payment) {
            $allPayment[$index]->allPayments = DB::select(
                $childSql,
                array_merge([$payment->paymentsID], $allUserIds)
            );
        }

        return response()->json([
            'success' => true,
            'data' => $allPayment,
        ]);
    }
}