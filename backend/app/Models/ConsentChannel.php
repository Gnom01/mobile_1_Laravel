<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Granularna zgoda per kanał (marketing e-mail / SMS / telefon / push,
 * profilowanie) z datą ostatniej zmiany.
 */
class ConsentChannel extends Model
{
    public const KEYS = [
        'marketing_email',
        'marketing_sms',
        'marketing_phone',
        'marketing_push',
        'profiling',
    ];

    protected $table = 'consent_channels';

    protected $fillable = [
        'UsersID',
        'consent_key',
        'granted',
        'changed_at',
    ];

    protected $casts = [
        'granted' => 'boolean',
        'changed_at' => 'datetime',
    ];
}
