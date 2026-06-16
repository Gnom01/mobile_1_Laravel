<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $table = 'contact_messages';
    protected $guarded = [];

    protected $casts = [
        'sender_user_id'    => 'integer',
        'recipient_user_id' => 'integer',
        'read_at'           => 'datetime',
    ];

    /** Klucz wątku — posortowana para UsersID (stały niezależnie od kierunku). */
    public static function conversationKey(int $a, int $b): string
    {
        $lo = min($a, $b);
        $hi = max($a, $b);
        return "{$lo}-{$hi}";
    }
}
