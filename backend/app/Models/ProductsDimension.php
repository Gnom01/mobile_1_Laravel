<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductsDimension extends Model
{
    protected $table = 'productsdimensions';
    protected $primaryKey = 'productsdimensionsid';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];
}
