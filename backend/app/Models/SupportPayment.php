<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportPayment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'due_date' => 'date:Y-m-d',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SupportSubscription::class, 'support_subscription_id');
    }
}
