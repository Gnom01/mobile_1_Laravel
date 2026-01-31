<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmUser extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'UsersID';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'WhenInserted' => 'datetime',
        'WhenUpdated' => 'datetime',
        'ActivationDate' => 'datetime',
        'WhenStatusUpdated' => 'datetime',
        'PassResetExpiration' => 'datetime',
        'DateOfBirdth' => 'date',
    ];
}
