<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Camp extends Model
{
    protected $table = 'camps';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    protected $guarded = [];

    protected $casts = [
        'starts_at'         => 'date',
        'ends_at'           => 'date',
        'next_event_date'   => 'date',
        'crm_updated_at'    => 'datetime',
        'raw_crm_payload'   => 'array',
        'transport_options' => 'array',
        'diet_options'      => 'array',
        'is_closed'         => 'boolean',
        'medical_required'  => 'boolean',
        'guardian_required' => 'boolean',
        'available_places'  => 'integer',
        'capacity'          => 'integer',
    ];
}
