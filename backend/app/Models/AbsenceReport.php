<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsenceReport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'event_date' => 'date:Y-m-d',
        'synced_at' => 'datetime',
    ];
}
