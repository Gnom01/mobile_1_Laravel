<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutboxEvent;
use App\Models\SupportPayment;
use App\Models\SupportSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Program wsparcia Fundacji Świat Tańca (SUP_01–SUP_03 z planu Etapu I).
 *
 * Subskrypcja jest prowadzona lokalnie po stronie mobile (źródło: aplikacja),
 * a każda zmiana stanu trafia do outbox_events pod przyszłą synchronizację
 * z CRM. Realne obciążenia cykliczne wymagają integracji z operatorem
 * płatności — do tego czasu historia wpłat wypełnia się wyłącznie realnie
 * zaksięgowanymi wpłatami (tabela support_payments).
 */
class SupportProgramController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        // Przy wyłączonej subskrypcji zakładka jest wyłącznie informacyjna:
        // zwracamy treści (impact/benefits) bez żadnych danych płatniczych.
        if (!$this->subscriptionEnabled()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'subscriptionEnabled' => false,
                    'monthlyAmount' => $this->monthlyAmount(),
                    'subscription' => null,
                    'totalPaid' => 0,
                    'impact' => config('services.support_program.impact'),
                    'benefits' => config('services.support_program.benefits'),
                    'history' => [],
                ],
            ]);
        }

        $user = $request->user();
        $subscription = SupportSubscription::where('users_id', (int) $user->UsersID)->first();

        $totalPaid = 0.0;
        $payments = collect();

        if ($subscription) {
            $payments = $subscription->payments()
                ->orderByRaw('COALESCE(paid_at, due_date, created_at) DESC')
                ->limit(24)
                ->get();
            $totalPaid = (float) $subscription->payments()
                ->where('status', 'paid')
                ->sum('amount');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'subscriptionEnabled' => true,
                'monthlyAmount' => $this->monthlyAmount(),
                'subscription' => $subscription ? $this->presentSubscription($subscription) : null,
                'totalPaid' => round($totalPaid, 2),
                'impact' => config('services.support_program.impact'),
                'benefits' => config('services.support_program.benefits'),
                'history' => $payments->map(fn (SupportPayment $p) => $this->presentPayment($p))->values(),
            ],
        ]);
    }

    public function join(Request $request): JsonResponse
    {
        if ($disabled = $this->rejectWhenDisabled()) {
            return $disabled;
        }

        $user = $request->user();
        $userId = (int) $user->UsersID;

        $subscription = SupportSubscription::where('users_id', $userId)->first();

        if ($subscription && $subscription->status === SupportSubscription::STATUS_ACTIVE) {
            return response()->json([
                'success' => true,
                'alreadyActive' => true,
                'data' => ['subscription' => $this->presentSubscription($subscription)],
            ]);
        }

        // Termin = dziś: najbliższy przebieg support:process-renewals naliczy
        // pierwszą wpłatę (pending) od razu, nie dopiero za miesiąc.
        if ($subscription) {
            // Reaktywacja po pauzie/rezygnacji.
            $subscription->update([
                'status' => SupportSubscription::STATUS_ACTIVE,
                'paused_at' => null,
                'cancelled_at' => null,
                'next_payment_at' => now()->toDateString(),
                'monthly_amount' => $this->monthlyAmount(),
            ]);
            $action = 'resumed';
        } else {
            $subscription = SupportSubscription::create([
                'users_id' => $userId,
                'status' => SupportSubscription::STATUS_ACTIVE,
                'monthly_amount' => $this->monthlyAmount(),
                'started_at' => now(),
                'next_payment_at' => now()->toDateString(),
                'payment_method' => null,
            ]);
            $action = 'created';
        }

        $this->pushOutbox($subscription, $action);

        Log::info('Support program join', ['users_id' => $userId, 'action' => $action]);

        return response()->json([
            'success' => true,
            'message' => 'Dziękujemy! Wspierasz teraz rozwój młodych tancerzy.',
            'data' => ['subscription' => $this->presentSubscription($subscription)],
        ], 201);
    }

    public function pause(Request $request): JsonResponse
    {
        if ($disabled = $this->rejectWhenDisabled()) {
            return $disabled;
        }

        return $this->transition(
            $request,
            SupportSubscription::STATUS_PAUSED,
            'Program wsparcia został wstrzymany.',
            fn (SupportSubscription $s) => $s->update([
                'status' => SupportSubscription::STATUS_PAUSED,
                'paused_at' => now(),
            ]),
        );
    }

    public function resume(Request $request): JsonResponse
    {
        if ($disabled = $this->rejectWhenDisabled()) {
            return $disabled;
        }

        // Termin = dziś — rozliczenie wznawia się od dnia wznowienia.
        return $this->transition(
            $request,
            SupportSubscription::STATUS_ACTIVE,
            'Wsparcie zostało wznowione. Dziękujemy!',
            fn (SupportSubscription $s) => $s->update([
                'status' => SupportSubscription::STATUS_ACTIVE,
                'paused_at' => null,
                'next_payment_at' => now()->toDateString(),
            ]),
        );
    }

    public function cancel(Request $request): JsonResponse
    {
        if ($disabled = $this->rejectWhenDisabled()) {
            return $disabled;
        }

        return $this->transition(
            $request,
            SupportSubscription::STATUS_CANCELLED,
            'Subskrypcja została anulowana. Wsparcie pozostanie aktywne do końca opłaconego okresu.',
            fn (SupportSubscription $s) => $s->update([
                'status' => SupportSubscription::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'next_payment_at' => null,
            ]),
        );
    }

    public function history(Request $request): JsonResponse
    {
        if ($disabled = $this->rejectWhenDisabled()) {
            return $disabled;
        }

        $user = $request->user();
        $subscription = SupportSubscription::where('users_id', (int) $user->UsersID)->first();

        if (!$subscription) {
            return response()->json(['success' => true, 'body' => []]);
        }

        $payments = $subscription->payments()
            ->orderByRaw('COALESCE(paid_at, due_date, created_at) DESC')
            ->get()
            ->map(fn (SupportPayment $p) => $this->presentPayment($p))
            ->values();

        return response()->json(['success' => true, 'body' => $payments]);
    }

    private function transition(
        Request $request,
        string $targetStatus,
        string $message,
        callable $apply
    ): JsonResponse {
        $user = $request->user();
        $subscription = SupportSubscription::where('users_id', (int) $user->UsersID)->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'error' => 'NOT_FOUND',
                'message' => 'Nie masz aktywnej subskrypcji programu wsparcia.',
            ], 404);
        }

        if ($subscription->status === $targetStatus) {
            return response()->json([
                'success' => true,
                'data' => ['subscription' => $this->presentSubscription($subscription)],
            ]);
        }

        $apply($subscription);
        $subscription->refresh();

        $this->pushOutbox($subscription, $targetStatus);

        Log::info('Support program transition', [
            'users_id' => (int) $user->UsersID,
            'status' => $targetStatus,
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['subscription' => $this->presentSubscription($subscription)],
        ]);
    }

    private function presentSubscription(SupportSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'monthlyAmount' => (float) $subscription->monthly_amount,
            'startedAt' => $subscription->started_at?->format('Y-m-d'),
            'pausedAt' => $subscription->paused_at?->format('Y-m-d'),
            'cancelledAt' => $subscription->cancelled_at?->format('Y-m-d'),
            'nextPaymentAt' => $subscription->next_payment_at?->format('Y-m-d'),
            'paymentMethod' => $subscription->payment_method,
        ];
    }

    private function presentPayment(SupportPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'type' => $payment->type,
            'status' => $payment->status,
            'paidAt' => $payment->paid_at?->format('Y-m-d'),
            'dueDate' => $payment->due_date?->format('Y-m-d'),
        ];
    }

    private function pushOutbox(SupportSubscription $subscription, string $action): void
    {
        OutboxEvent::create([
            'entity' => 'support_subscriptions',
            'action' => $action,
            'local_id' => $subscription->id,
            'payload' => [
                'usersID' => (int) $subscription->users_id,
                'status' => $subscription->status,
                'monthlyAmount' => (float) $subscription->monthly_amount,
                'startedAt' => $subscription->started_at?->format('Y-m-d'),
                'nextPaymentAt' => $subscription->next_payment_at?->format('Y-m-d'),
            ],
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    private function monthlyAmount(): float
    {
        return (float) config('services.support_program.monthly_amount', 5.00);
    }

    private function subscriptionEnabled(): bool
    {
        return (bool) config('services.support_program.enabled', false);
    }

    /**
     * 403 dla endpointów czysto subskrypcyjnych, gdy moduł płatny jest
     * wyłączony (SUPPORT_PROGRAM_ENABLED=false). Zakładka informacyjna
     * (status) działa dalej.
     */
    private function rejectWhenDisabled(): ?JsonResponse
    {
        if ($this->subscriptionEnabled()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => 'SUBSCRIPTION_DISABLED',
            'message' => 'Subskrypcja programu wsparcia jest obecnie niedostępna.',
        ], 403);
    }
}
