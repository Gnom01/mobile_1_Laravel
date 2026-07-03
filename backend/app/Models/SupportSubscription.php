<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportSubscription extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'monthly_amount' => 'decimal:2',
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'next_payment_at' => 'date:Y-m-d',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(SupportPayment::class);
    }
}
