<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoursesHeading extends Model
{
    protected $table = 'coursesheadings';
    protected $primaryKey = 'CoursesHeadingsID';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];
}
