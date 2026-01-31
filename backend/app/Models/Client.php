<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $table = 'clients';
    protected $primaryKey = 'ClientsID';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'WhenInserted' => 'datetime',
        'WhenUpdated' => 'datetime',
    ];
}
