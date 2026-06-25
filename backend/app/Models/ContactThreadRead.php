<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactThreadRead extends Model
{
    protected $table = 'contact_thread_reads';
    protected $guarded = [];

    protected $casts = [
        'user_id'      => 'integer',
        'last_read_at' => 'datetime',
    ];
}
