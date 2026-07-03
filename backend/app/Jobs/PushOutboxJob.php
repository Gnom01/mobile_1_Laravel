<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PushOutboxJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Encje z odbiorcą po stronie CRM. Encje spoza listy (np. absence_reports,
     * support_subscriptions) dostają status `deferred` — czekają na powstanie
     * endpointu CRM, zamiast być fałszywie oznaczane jako wysłane albo
     * blokować początek kolejki (limit 100 wg id).
     * Po dodaniu endpointu: obsłuż encję poniżej i przywróć zdarzenia przez
     * UPDATE outbox_events SET status='pending' WHERE entity='...'.
     */
    private const HANDLED_ENTITIES = ['clients', 'users'];

    /**
     * Execute the job.
     */
    public function handle(\App\Services\CrmClient $crm)
    {
        \Illuminate\Support\Facades\Log::info("PushOutboxJob started");

        $events = \App\Models\OutboxEvent::where('status', 'pending')
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($events as $e) {
            if (!in_array($e->entity, self::HANDLED_ENTITIES, true)) {
                $e->status = 'deferred';
                $e->last_error = 'Brak endpointu CRM dla encji: ' . $e->entity;
                $e->save();
                continue;
            }

            try {
                $e->attempts++;

                // dopasuj endpointy CRM
                if ($e->entity === 'clients') {
                    // np. PUT /Clients/{id}
                    $crm->put('/Clients/' . $e->local_id, $e->payload);
                }

                if ($e->entity === 'users') {
                    $crm->put('/Users/' . $e->local_id, $e->payload);
                }

                $e->status = 'sent';
                $e->sent_at = now();
                $e->last_error = null;
                $e->save();
            } catch (\Throwable $ex) {
                $e->status = 'failed';
                $e->last_error = $ex->getMessage();
                $e->save();
            }
        }

        \Illuminate\Support\Facades\Log::info("PushOutboxJob finished");
    }

}