<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'ProductsID';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'StartingDate'    => 'date',
        'ClosingDate'     => 'date',
        'ExpirationDate'  => 'date',
        'WhenInserted'    => 'datetime',
        'WhenUpdated'     => 'datetime',
        'UnitPrice'       => 'decimal:2',
        'Price'           => 'decimal:2',
        'Cancelled'       => 'integer',
        'hidden'          => 'integer',
        'isDeposit'       => 'integer',
    ];
}
