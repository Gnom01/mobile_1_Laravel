<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomReservation extends Model
{
    protected $table = 'room_reservations';

    protected $guarded = [];

    protected $casts = [
        'reservation_date' => 'date',
        'expires_at'       => 'datetime',
        'confirmed_at'     => 'datetime',
        'cancelled_at'     => 'datetime',
    ];

    public const STATUS_PENDING = 'pending_payment';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /** Statusy blokujące slot (dla kolizji przy tworzeniu nowej rezerwacji). */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_PARTIALLY_PAID,
    ];

    public function participants(): HasMany
    {
        return $this->hasMany(RoomReservationParticipant::class);
    }
}
