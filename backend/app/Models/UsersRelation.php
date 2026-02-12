<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersRelation extends Model
{
    protected $table = 'usersrelations';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'DateFrom' => 'date',
        'DateTo' => 'date',
        'WhenInserted' => 'datetime',
        'WhenUpdated' => 'datetime',
    ];
}
