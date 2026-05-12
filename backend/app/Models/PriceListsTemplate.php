<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceListsTemplate extends Model
{
    protected $table = 'priceliststemplates';
    protected $primaryKey = 'priceliststemplatesid';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];
}
