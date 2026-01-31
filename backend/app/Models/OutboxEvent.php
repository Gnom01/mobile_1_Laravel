<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    protected $guarded = [];
    protected $casts = ['payload' => 'array', 'sent_at' => 'datetime'];
}
