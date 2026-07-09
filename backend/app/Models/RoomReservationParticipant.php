<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomReservationParticipant extends Model
{
    protected $table = 'room_reservation_participants';

    protected $guarded = [];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(RoomReservation::class, 'room_reservation_id');
    }
}
