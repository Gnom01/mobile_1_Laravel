<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkshopYgm extends Model
{
    protected $table = 'workshops_ygm';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    protected $guarded = [];

    protected $casts = [
        'starts_at'        => 'date',
        'ends_at'          => 'date',
        'next_event_date'  => 'date',
        'crm_updated_at'   => 'datetime',
        'raw_crm_payload'  => 'array',
        'is_closed'        => 'boolean',
        'available_places' => 'integer',
        'capacity'         => 'integer',
    ];
}
