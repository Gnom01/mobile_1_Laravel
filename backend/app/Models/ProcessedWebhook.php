<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency store for incoming webhooks (e.g. payment provider callbacks).
 *
 * @property int         $id
 * @property string      $provider
 * @property string      $event_id
 * @property array       $payload_json
 * @property \Carbon\Carbon|null $processed_at
 */
class ProcessedWebhook extends Model
{
    protected $table = 'processed_webhooks';

    protected $guarded = [];

    protected $casts = [
        'payload_json' => 'array',
        'processed_at' => 'datetime',
    ];
}
