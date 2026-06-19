<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorChatMessage extends Model
{
    protected $table = 'instructor_chat_messages';
    protected $guarded = [];

    protected $casts = [
        'chat_id'        => 'integer',
        'sender_user_id' => 'integer',
    ];
}
