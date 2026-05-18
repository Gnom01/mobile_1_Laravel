<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersSchedule extends Model
{
    protected $table = 'usersschedules';
    protected $primaryKey = 'usersschedulesid';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];
}
