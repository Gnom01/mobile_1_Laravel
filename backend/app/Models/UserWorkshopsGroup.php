<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWorkshopsGroup extends Model
{
    protected $table = 'userworkshopsgroups';
    protected $primaryKey = 'userworkshopsgroupsid';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];
}
