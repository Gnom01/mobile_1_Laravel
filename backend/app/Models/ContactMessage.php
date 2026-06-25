<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $table = 'contact_messages';
    protected $guarded = [];

    protected $casts = [
        'participant_user_id' => 'integer',
        'instructor_user_id'  => 'integer',
        'sender_user_id'      => 'integer',
    ];

    /** Klucz wątku — zakotwiczony na uczestniku (dziecku) i instruktorze. */
    public static function threadKey(int $participantUserId, int $instructorUserId): string
    {
        return "p{$participantUserId}-i{$instructorUserId}";
    }
}
