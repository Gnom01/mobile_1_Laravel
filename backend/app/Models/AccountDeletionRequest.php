<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Żądanie usunięcia Konta potwierdzone kodem SMS (część V dokumentu
 * prawnego). Status "confirmed" blokuje logowanie do czasu realizacji
 * (processed) albo anulowania przez administrację (cancelled).
 */
class AccountDeletionRequest extends Model
{
    protected $table = 'account_deletion_requests';

    protected $fillable = [
        'UsersID',
        'status',
        'requested_at',
        'confirmed_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public static function isBlocked(int $usersId): bool
    {
        return static::where('UsersID', $usersId)
            ->where('status', 'confirmed')
            ->exists();
    }
}
