<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorChat extends Model
{
    protected $table = 'instructor_chats';
    protected $guarded = [];

    protected $casts = [
        'instructor_user_id' => 'integer',
        'member_count'       => 'integer',
    ];

    public function members()
    {
        return $this->hasMany(InstructorChatMember::class, 'chat_id');
    }

    public function messages()
    {
        return $this->hasMany(InstructorChatMessage::class, 'chat_id');
    }
}
