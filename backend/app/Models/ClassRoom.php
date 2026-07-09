<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassRoom extends Model
{
    protected $table = 'classrooms';

    protected $primaryKey = 'classRoomsID';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
