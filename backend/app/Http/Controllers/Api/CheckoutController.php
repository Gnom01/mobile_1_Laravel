<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Order\CrmOrderException;
use App\Http\Controllers\Controller;
use App\Jobs\PullPaymentsItemsJob;
use App\Jobs\PullPaymentsJob;
use App\Jobs\PullPaymentsRealJob;
use App\Models\CheckoutSession;
use App\Models\PaymentItem;
use App\Models\UsersPaymentsSchedule;
use App\Models\UsersRelation;
use App\Services\Order\CrmOrderClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function startScheduleCheckout(Request $request, CrmOrderClient $crmClient)
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

        // Ponowna próba dla tych samych rat: pierwsze wywołanie utworzyło już
        // płatność online w CRM i kolejne kończy się tam błędem „Błędna kwota
        // płatności online" (dopóki CRON CRM nie anuluje wiszącej transakcji).
        // Zwracamy więc link z istniejącej sesji zamiast dublować płatność.
        $sortedIds = $scheduleIds->sort()->values()->all();
        $existing = CheckoutSession::query()
            ->where('crm_user_id', $user->UsersID)
            ->where('type', 'schedule_payment')
            ->where('status', 'pending_payment')
            ->whereNotNull('redirect_url')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest('id')
            ->get()
            ->first(fn ($cs) => collect($cs->selected_schedule_ids)
                ->map(fn ($id) => (int) $id)->sort()->values()->all() === $sortedIds);

        if ($existing) {
            return response()->json([
                'success' => true,
                'checkout' => [
                    'id' => $existing->id,
                    'status' => $existing->status,
                    'amount' => (float) $existing->amount,
                    'redirect_url' => $existing->redirect_url,
                    'crm_session_id' => $existing->crm_session_id,
                    'crm_payment_token' => $existing->crm_payment_token,
                    'resumed' => true,
                ],
            ]);
        }

        // Flutter potrafi wysłać return_url='' — '??' nie łapie pustego stringa
        $returnUrl = trim((string) ($validated['return_url'] ?? ''));
        if ($returnUrl === '') {
            $returnUrl = (string) (config('services.crm.mobile_checkout_return_url') ?? config('app.url'));
        }

        // Raty mogą należeć do różnych uczestników (dzieci), płatnikiem jest rodzic
        $entries = $schedules->map(fn ($s) => [
            'usersID' => (int) $s->usersID,
            'scheduleID' => (int) $s->usersPaymentsSchedulesID,
        ])->all();

        $guid = (string) Str::uuid();

        try {
            $body = $crmClient->initiateSchedulePayment(
                $entries,
                (int) $user->UsersID,
                (int) ($validated['payment_method'] ?? 5),
                $returnUrl,
                $guid,
                trim((string) ($validated['buyer_nip'] ?? ''))
            );
        } catch (CrmOrderException $e) {
            // Błąd biznesowy CRM — pokazujemy konkretną przyczynę, nie ogólnik
            Log::error('Schedule checkout: CRM rejected payment', [
                'guid' => $guid,
                'user_id' => $user->UsersID,
                'crm_status' => $e->getCode(),
                'crm_message' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'CRM odrzucił płatność: ' . $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            Log::error('Schedule checkout: CRM payment initiation failed', [
                'guid' => $guid,
                'user_id' => $user->UsersID,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się zainicjować płatności. Spróbuj ponownie za chwilę.',
            ], 502);
        }

        $token = $body['token'] ?? $body['html'] ?? null;
        $sessionId = $body['sessionID'] ?? $body['sessionId'] ?? null;
        $redirectUrl = $body['redirect_url'] ?? $body['payment_url'] ?? $body['url'] ?? null;

        // CRM zwraca token P24/PayNow albo od razu pełny URL — jak w pozostałych
        // torach płatności mobile budujemy link z szablonu tylko dla gołego tokena.
        if (!$redirectUrl && $token) {
            if (str_starts_with((string) $token, 'http')) {
                $redirectUrl = (string) $token;
            } else {
                $template = (string) config('services.crm.payment_token_url_template', '');
                if ($template !== '') {
                    $redirectUrl = Str::replace('{token}', (string) $token, $template);
                }
            }
        }

        if (!$redirectUrl) {
            Log::error('Schedule checkout: CRM returned no payment link', [
                'guid' => $guid,
                'user_id' => $user->UsersID,
                'crm_body' => $body,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'CRM nie zwrócił linku płatności. Spróbuj ponownie za chwilę.',
            ], 502);
        }

        $checkout = CheckoutSession::create([
            'crm_user_id' => $user->UsersID,
            'type' => 'schedule_payment',
            'status' => 'pending_payment',
            'localization_id' => $localizationIds->first(),
            'amount' => $amount,
            'selected_schedule_ids' => $scheduleIds->all(),
            'crm_session_id' => $sessionId,
            'crm_payment_token' => $token,
            'redirect_url' => $redirectUrl,
            'remote_payload' => $body,
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
