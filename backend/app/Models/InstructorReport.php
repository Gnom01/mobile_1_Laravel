<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorReport extends Model
{
    protected $table = 'instructor_reports';
    protected $guarded = [];

    protected $casts = [
        'instructor_user_id'     => 'integer',
        'report_types'           => 'array',
        'group_ids'              => 'array',
        'event_date'             => 'date',
        'manager_notified_count' => 'integer',
    ];
}
