<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorChatMember extends Model
{
    protected $table = 'instructor_chat_members';
    protected $guarded = [];

    protected $casts = [
        'chat_id' => 'integer',
        'user_id' => 'integer',
    ];
}
