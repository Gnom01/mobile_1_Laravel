<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PullPaymentsItemsJob;
use App\Jobs\PullPaymentsJob;
use App\Jobs\PullPaymentsRealJob;
use App\Models\CheckoutSession;
use App\Models\PaymentItem;
use App\Models\UsersPaymentsSchedule;
use App\Models\UsersRelation;
use App\Services\CrmPortalCheckoutService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function startScheduleCheckout(Request $request, CrmPortalCheckoutService $crmCheckout)
    {
        $validated = $request->validate([
            'schedule_ids' => 'required|array|min:1',
            'schedule_ids.*' => 'integer|min:1',
            'payment_method' => 'nullable|integer',
            'buyer_nip' => 'nullable|string|max:32',
            'return_url' => 'nullable|string|max:2048',
        ]);

        $user = $request->user();
        $allowedUserIds = UsersRelation::where('Parent_UsersID', $user->UsersID)
            ->where('Cancelled', 0)
            ->pluck('UsersID')
            ->prepend($user->UsersID)
            ->unique()
            ->values();

        $scheduleIds = collect($validated['schedule_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $schedules = UsersPaymentsSchedule::query()
            ->whereIn('usersPaymentsSchedulesID', $scheduleIds)
            ->whereIn('usersID', $allowedUserIds)
            ->where('cancelled', 0)
            ->where('paymentStatusesDVID', 1)
            ->get();

        if ($schedules->count() !== $scheduleIds->count()) {
            return response()->json([
                'success' => false,
                'message' => 'Część rat nie istnieje, nie jest nieopłacona albo nie należy do tego użytkownika.',
            ], 422);
        }

        $localizationIds = $schedules->pluck('localizationsID')->filter()->unique()->values();
        if ($localizationIds->count() !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'W jednej płatności mobilnej można opłacić raty tylko z jednej szkoły.',
            ], 422);
        }

        $amount = (float) $schedules->sum('paymentAmount');
        $returnUrl = $validated['return_url']
            ?? config('services.crm.mobile_checkout_return_url')
            ?? config('app.url');

        $portalResult = $crmCheckout->startSchedulePayment(
            $user,
            $schedules,
            (string) $returnUrl,
            (int) ($validated['payment_method'] ?? 5),
            $validated['buyer_nip'] ?? null
        );

        $checkout = CheckoutSession::create([
            'crm_user_id' => $user->UsersID,
            'type' => 'schedule_payment',
            'status' => 'pending_payment',
            'localization_id' => $localizationIds->first(),
            'amount' => $amount,
            'selected_schedule_ids' => $scheduleIds->all(),
            'crm_session_id' => $portalResult['crm_session_id'],
            'crm_payment_token' => $portalResult['crm_payment_token'],
            'redirect_url' => $portalResult['redirect_url'],
            'remote_payload' => $portalResult['raw'],
        ]);

        return response()->json([
            'success' => true,
            'checkout' => [
                'id' => $checkout->id,
                'status' => $checkout->status,
                'amount' => (float) $checkout->amount,
                'redirect_url' => $checkout->redirect_url,
                'crm_session_id' => $checkout->crm_session_id,
                'crm_payment_token' => $checkout->crm_payment_token,
            ],
        ]);
    }

    public function refreshStatus(Request $request, CheckoutSession $checkoutSession)
    {
        $user = $request->user();
        if ((int) $checkoutSession->crm_user_id !== (int) $user->UsersID) {
            return response()->json([
                'success' => false,
                'message' => 'Brak dostępu do tej sesji checkout.',
            ], 403);
        }

        PullPaymentsJob::dispatchSync();
        PullPaymentsRealJob::dispatchSync();
        PullPaymentsItemsJob::dispatchSync();

        $selectedIds = collect($checkoutSession->selected_schedule_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $matchingPaymentItems = PaymentItem::query()
            ->whereIn('usersPaymentsSchedulesID', $selectedIds)
            ->where('cancelled', 0)
            ->count();

        if ($matchingPaymentItems > 0 && $checkoutSession->status !== 'paid') {
            $checkoutSession->status = 'paid';
            $checkoutSession->paid_at = now();
        }

        $checkoutSession->sync_refreshed_at = now();
        $checkoutSession->save();

        return response()->json([
            'success' => true,
            'checkout' => [
                'id' => $checkoutSession->id,
                'status' => $checkoutSession->status,
                'amount' => (float) $checkoutSession->amount,
                'redirect_url' => $checkoutSession->redirect_url,
                'paid_at' => $checkoutSession->paid_at
                    ? $checkoutSession->paid_at->toIso8601String()
                    : null,
                'sync_refreshed_at' => $checkoutSession->sync_refreshed_at
                    ? $checkoutSession->sync_refreshed_at->toIso8601String()
                    : null,
                'matched_payment_items' => $matchingPaymentItems,
            ],
        ]);
    }
}
