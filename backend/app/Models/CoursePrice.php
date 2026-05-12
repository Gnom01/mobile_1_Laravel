<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursePrice extends Model
{
    protected $table = 'course_prices';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'coursesHeadingsID', 'coursesHeadingsID');
    }
}
