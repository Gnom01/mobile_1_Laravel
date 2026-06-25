<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorChatRead extends Model
{
    protected $table = 'instructor_chat_reads';
    protected $guarded = [];

    protected $casts = [
        'chat_id'      => 'integer',
        'user_id'      => 'integer',
        'last_read_at' => 'datetime',
    ];
}
