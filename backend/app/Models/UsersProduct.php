<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersProduct extends Model
{
    protected $table = 'usersproducts';
    protected $primaryKey = 'usersproductsid';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];
}
