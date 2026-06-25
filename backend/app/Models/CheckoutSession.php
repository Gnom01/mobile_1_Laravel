<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutSession extends Model
{
    protected $guarded = [];

    protected $casts = [
        'selected_schedule_ids' => 'array',
        'remote_payload' => 'array',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'sync_refreshed_at' => 'datetime',
    ];
}
