<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoursesHeadingsDimension extends Model
{
    protected $table = 'coursesheadingsdimensions';
    protected $primaryKey = 'coursesheadingsdimensionsid';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];
}
