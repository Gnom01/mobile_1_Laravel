<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncRecordFailure extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];
}
