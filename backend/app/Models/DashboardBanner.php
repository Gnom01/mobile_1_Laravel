<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DashboardBanner extends Model
{
    public const ACTION_OFFERS = 'offers';
    public const ACTION_PAYMENTS = 'payments';
    public const ACTION_SCHEDULE = 'schedule';
    public const ACTION_NOTIFICATIONS = 'notifications';
    public const ACTION_URL = 'url';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->ordered();
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
