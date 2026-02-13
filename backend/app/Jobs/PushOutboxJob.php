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
