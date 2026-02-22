<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dictionary extends Model
{
    protected $table = 'dictionaries';
    protected $primaryKey = 'DictionariesID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'DictionariesID',
        'Parent_DictionariesID',
        'Parent_DictionaryName',
        'Parent_ValueID',
        'DictionaryName',
        'Name',
        'ValueID',
        'ValueText',
        'OrderPosition',
        'Description',
        'Editable',
        'Cancelled',
        'WhenInserted',
        'WhoInserted_UsersID',
        'WhenUpdated',
        'WhoUpdated_UsersID',
        'ItemColor',
        'Hidden',
    ];

    protected $casts = [
        'WhenInserted' => 'datetime',
        'WhenUpdated' => 'datetime',
        'Editable' => 'integer',
        'Cancelled' => 'integer',
        'Hidden' => 'integer',
    ];
}
