<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncState extends Model
{
    protected $guarded = [];
    protected $casts = [
        'last_sync_at' => 'datetime',
        'is_full_synced' => 'boolean',
        'full_sync_started_at' => 'datetime',
        'full_sync_completed_at' => 'datetime',
    ];
}
