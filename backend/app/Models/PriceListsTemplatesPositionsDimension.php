<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceListsTemplatesPositionsDimension extends Model
{
    protected $table = 'priceliststemplatespositionsdimensions';
    protected $primaryKey = 'priceliststemplatespositionsdimensionsid';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];
}
