<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Localization extends Model
{
    protected $table = 'localizations';
    protected $primaryKey = 'LocalizationsID';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'WhenInserted' => 'datetime',
        'WhenUpdated' => 'datetime',
        'Cancelled' => 'integer',
        'Hidden' => 'integer',
    ];
}
