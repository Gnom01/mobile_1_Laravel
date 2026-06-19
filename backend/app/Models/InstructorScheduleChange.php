<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorScheduleChange extends Model
{
    protected $table = 'instructor_schedule_changes';
    protected $guarded = [];

    protected $casts = [
        'instructor_user_id'     => 'integer',
        'change_types'           => 'array',
        'group_ids'              => 'array',
        'event_date'             => 'date',
        'recipient_count'        => 'integer',
        'manager_notified_count' => 'integer',
    ];
}
