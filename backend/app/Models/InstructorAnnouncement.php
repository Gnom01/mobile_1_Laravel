<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorAnnouncement extends Model
{
    protected $table = 'instructor_announcements';
    protected $guarded = [];

    protected $casts = [
        'localizations_id' => 'integer',
        'event_at'         => 'datetime',
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
        'is_active'        => 'boolean',
        'sort_order'       => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
