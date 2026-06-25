<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncRunLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'last_sync_at_before' => 'datetime',
        'last_sync_at_after' => 'datetime',
    ];
}
