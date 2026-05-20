<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'tickets';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    protected $guarded = [];

    protected $casts = [
        'starts_at'        => 'date',
        'ends_at'          => 'date',
        'next_event_date'  => 'date',
        'sale_starts_at'   => 'datetime',
        'sale_ends_at'     => 'datetime',
        'crm_updated_at'   => 'datetime',
        'raw_crm_payload'  => 'array',
        'is_closed'        => 'boolean',
        'price_from'       => 'decimal:2',
        'available_places' => 'integer',
        'capacity'         => 'integer',
    ];
}
