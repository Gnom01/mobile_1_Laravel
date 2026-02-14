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

        \App\Jobs\PullPaymentsJob::dispatchSync();

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
}
