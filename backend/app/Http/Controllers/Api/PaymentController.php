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
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Brak autoryzacji'], 401);
        }

        $usersID = $user->UsersID;

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
                'userspaymentsschedules.localizationsID'
            )
            ->join('users as u', 'u.UsersID', '=', 'userspaymentsschedules.usersID')
            ->whereIn('userspaymentsschedules.usersID', $allUserIds)
            ->where('userspaymentsschedules.Cancelled', 0)
            ->where('userspaymentsschedules.paymentStatusesDVID', 1)
            ->orderBy('userspaymentsschedules.orderValue')
            ->orderBy('userspaymentsschedules.paymentStatusesDVID')
            ->orderBy('userspaymentsschedules.paymentDate')
            ->get();

        // Group by localizationsID
        $groupedPayments = $payments->groupBy('localizationsID');

        return response()->json($groupedPayments);
    }

    /**
     * Get payment history for a user (and their related users).
     *
     * @param string $parentGuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentHistory($parentGuid)
    {
        // 1. Resolve usersID from guid
        $user = DB::table('users')->where('guid', $parentGuid)->where('Cancelled', 0)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $usersID = $user->UsersID;

        // 2. Fetch Parent Payments
        $parentSql = "
            SELECT 
                p.usersID, p.paymentsID, p.paymentDate, p.cancelled, p.paymentMethodsDVID,
                d.Name AS paymentMethodsName, p.recepcionist_UsersID, u.fullName,
                p.localizationsID, l.clientsID, l.localizationName,
                p.paymentAmount AS paymentItemAmount,
                d1.Name AS productPaymentsName,
                p.paymentStatusesDVID, d3.Name AS paymentStatusesName
            FROM payments p
            LEFT JOIN dictionaries d  ON d.valueID = p.paymentMethodsDVID AND d.DictionaryName = 'PaymentMethods' AND d.Cancelled = 0
            LEFT JOIN dictionaries d1 ON d1.DictionaryName = 'paymentTypes' AND d1.ValueID = p.paymentMethodsDVID AND d1.Cancelled = 0
            LEFT JOIN dictionaries d3 ON d3.DictionaryName = 'PaymentStatuses' AND d3.ValueID = p.paymentStatusesDVID AND d3.Cancelled = 0
            LEFT JOIN localizations l ON l.LocalizationsID = p.localizationsID AND l.Cancelled = 0
            LEFT JOIN users u         ON u.UsersID = p.usersID AND u.Cancelled = 0
            WHERE p.cancelled = 0
                AND p.paymentStatusesDVID IN (1,2,3,4)
                AND p.paymentMethodsDVID <> 4
                AND (
                    p.usersID = :usersID1
                    OR p.payer_UsersID = :usersID2
                    OR EXISTS (
                        SELECT 1 FROM usersrelations ur
                        WHERE ur.Parent_UsersID = :usersID3      
                            AND ur.UsersID = p.payer_UsersID  
                            AND ur.Cancelled = 0
                            AND ur.ParticipantRelationsDVID IN (1,2)
                    )
                )
            ORDER BY p.paymentsID DESC
        ";

        $allPayment = DB::select($parentSql, [
            'usersID1' => $usersID,
            'usersID2' => $usersID,
            'usersID3' => $usersID
        ]);

        // 3. For each payment, fetch child items
        foreach ($allPayment as $index => $payment) {
            $childSql = "
                WITH family AS (
                    SELECT :usersID1 AS UsersID
                    UNION
                    SELECT ur.UsersID
                    FROM usersrelations ur
                    WHERE ur.Parent_UsersID = :usersID2
                    AND ur.Cancelled = 0
                )
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
                JOIN family f ON f.UsersID = pis.usersID
                LEFT JOIN payments p ON p.paymentsID = pis.paymentsID AND p.cancelled = 0
                LEFT JOIN dictionaries d ON d.valueID = p.paymentMethodsDVID AND d.DictionaryName = 'PaymentMethods' AND d.Cancelled = 0
                LEFT JOIN users u ON p.usersID = u.UsersID AND u.Cancelled = 0
                LEFT JOIN localizations l ON l.LocalizationsID = pis.localizationsID AND l.Cancelled = 0
                LEFT JOIN products pr ON pr.ProductsID = pis.productsID AND pr.Cancelled = 0
                LEFT JOIN dictionaries d1 ON d1.DictionaryName = 'paymentTypes' AND pr.paymentTypesDVID = d1.ValueID AND d1.Cancelled = 0
                LEFT JOIN dictionaries d2 ON d2.DictionaryName = 'ProductsLevel2' AND pr.ProductsLevel2DVID = d2.ValueID AND d2.Cancelled = 0
                LEFT JOIN userspaymentsschedules ups ON ups.usersPaymentsSchedulesID = pis.usersPaymentsSchedulesID AND ups.cancelled = 0
                LEFT JOIN dictionaries d3 ON d3.DictionaryName = 'PaymentStatuses' AND p.paymentStatusesDVID = d3.ValueID AND d3.Cancelled = 0
                WHERE p.paymentsID = :paymentsID
            ";

            $allPayment[$index]->allPayments = DB::select($childSql, [
                'usersID1' => $usersID,
                'usersID2' => $usersID,
                'paymentsID' => $payment->paymentsID
            ]);
        }

        return response()->json($allPayment);
    }
}
