<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DaysOff extends Model
{
    protected $table = 'daysoff';
    protected $primaryKey = 'daysoffid';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];
}
