<?php

namespace App\Console\Commands;

use App\Models\OutboxEvent;
use App\Models\SupportPayment;
use App\Models\SupportSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Cykl miesięczny programu wsparcia: dla aktywnych subskrypcji z terminem
 * płatności <= dziś nalicza wpłatę `pending` za bieżący okres, przesuwa
 * next_payment_at o miesiąc i emituje zdarzenie `billing_due` do outboxu.
 *
 * Komenda NIE pobiera pieniędzy — obciążenie wykona integracja z operatorem
 * płatności (decyzja PayNow/Tpay w toku); wpis `pending` + zdarzenie outbox
 * są dla niej zleceniem. Idempotencja: unikat (subskrypcja, due_date).
 */
class SupportProcessRenewals extends Command
{
    protected $signature = 'support:process-renewals {--dry-run : Tylko pokaż, co zostałoby naliczone}';

    protected $description = 'Nalicza miesięczne odnowienia programu wsparcia (wpłaty pending + zdarzenia billing_due)';

    public function handle(): int
    {
        $today = now()->toDateString();

        $due = SupportSubscription::where('status', SupportSubscription::STATUS_ACTIVE)
            ->whereNotNull('next_payment_at')
            ->whereDate('next_payment_at', '<=', $today)
            ->orderBy('id')
            ->get();

        if ($due->isEmpty()) {
            $this->info('Brak subskrypcji do odnowienia.');
            return self::SUCCESS;
        }

        $processed = 0;

        foreach ($due as $subscription) {
            $dueDate = $subscription->next_payment_at->format('Y-m-d');

            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '[dry-run] users_id=%d due_date=%s amount=%s',
                    $subscription->users_id,
                    $dueDate,
                    $subscription->monthly_amount,
                ));
                continue;
            }

            $payment = SupportPayment::firstOrCreate(
                [
                    'support_subscription_id' => $subscription->id,
                    'due_date' => $dueDate,
                ],
                [
                    'users_id' => (int) $subscription->users_id,
                    'amount' => $subscription->monthly_amount,
                    'type' => $subscription->payments()->count() === 0
                        ? 'first'
                        : 'recurring',
                    'status' => 'pending',
                ]
            );

            // Przesuwamy termin niezależnie od tego, czy wpis już istniał —
            // subskrypcja z next_payment_at w przeszłości nie może być
            // naliczana w kółko.
            $subscription->update([
                'next_payment_at' => $subscription->next_payment_at
                    ->copy()
                    ->addMonthNoOverflow()
                    ->toDateString(),
            ]);

            if ($payment->wasRecentlyCreated) {
                OutboxEvent::create([
                    'entity' => 'support_payments',
                    'action' => 'billing_due',
                    'local_id' => $payment->id,
                    'payload' => [
                        'usersID' => (int) $subscription->users_id,
                        'supportSubscriptionID' => $subscription->id,
                        'amount' => (float) $subscription->monthly_amount,
                        'dueDate' => $dueDate,
                        'type' => $payment->type,
                    ],
                    'idempotency_key' => (string) Str::uuid(),
                ]);
                $processed++;
            }
        }

        Log::info('support:process-renewals finished', [
            'due_count' => $due->count(),
            'created' => $processed,
        ]);

        $this->info("Naliczono odnowienia: {$processed} (kandydatów: {$due->count()}).");

        return self::SUCCESS;
    }
}
