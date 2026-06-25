<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulesEventSettlement extends Model
{
    protected $table = 'scheduleseventssettlements';
    protected $primaryKey = 'schedulesEventsSettlementsID';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];
}
