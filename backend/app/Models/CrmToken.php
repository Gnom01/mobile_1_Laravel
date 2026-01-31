<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmToken extends Model
{
    protected $guarded = [];

    protected $fillable = [
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
