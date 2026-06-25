<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'employees';
    protected $primaryKey = 'EmployeesID';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'DateOfBirdth'          => 'date',
        'StartDateCooperation'  => 'date',
        'EndDateCooperation'    => 'date',
        'WhenInserted'          => 'datetime',
        'WhenUpdated'           => 'datetime',
    ];
}
