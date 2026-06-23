<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorMessage extends Model
{
    protected $table = 'instructor_messages';
    protected $guarded = [];

    protected $casts = [
        'instructor_user_id'  => 'integer',
        'group_id'            => 'integer',
        'participant_user_id' => 'integer',
        'recipient_count'     => 'integer',
    ];
}
