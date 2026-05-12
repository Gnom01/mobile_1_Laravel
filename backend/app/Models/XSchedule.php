<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XSchedule extends Model
{
    protected $table = 'xschedules';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];
}
