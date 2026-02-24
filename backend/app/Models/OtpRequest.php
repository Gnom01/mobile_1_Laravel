<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpRequest extends Model
{
    protected $table = 'otp_requests';

    protected $fillable = [
        'phone',
        'code_hash',
        'expires_at',
        'attempts',
        'sent_message_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
