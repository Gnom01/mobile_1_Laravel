<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersTicket extends Model
{
    protected $table = 'userstickets';
    protected $primaryKey = 'usersticketsid';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];
}
