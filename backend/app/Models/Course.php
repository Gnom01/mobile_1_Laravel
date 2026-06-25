<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $table = 'courses';
    protected $primaryKey = 'coursesHeadingsID';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'startingDate'  => 'date',
        'closingDate'   => 'date',
        'startDateTime' => 'datetime',
        'eventDate'     => 'date',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(CoursePrice::class, 'coursesHeadingsID', 'coursesHeadingsID');
    }
}
