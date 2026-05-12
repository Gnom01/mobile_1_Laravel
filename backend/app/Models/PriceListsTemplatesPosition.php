<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceListsTemplatesPosition extends Model
{
    protected $table = 'priceliststemplatespositions';
    protected $primaryKey = 'priceliststemplatespositionsid';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];
}
