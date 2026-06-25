<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSegment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'filters_json' => 'array',
    ];
}
