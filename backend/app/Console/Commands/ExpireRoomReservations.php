<?php

namespace App\Console\Commands;

use App\Models\RoomReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Wygasza tymczasowe rezerwacje sal, których nikt nie opłacił w 15 minut.
 * Uruchamiane co minutę (routes/console.php). Zwolnienie slotu następuje
 * automatycznie — status 'expired' wypada z ACTIVE_STATUSES, więc slot
 * przestaje blokować dostępność.
 *
 * TODO (etap płatności): przed oznaczeniem expired anulować naliczenia CRM
 * nieopłaconych uczestników (users_payments_schedules_id).
 */
class ExpireRoomReservations extends Command
{
    protected $signature = 'room-reservations:expire';

    protected $description = 'Wygasza nieopłacone tymczasowe rezerwacje sal po 15 minutach';

    public function handle(): int
    {
        $expired = RoomReservation::query()
            ->where('status', RoomReservation::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->update([
                'status'       => RoomReservation::STATUS_EXPIRED,
                'cancelled_at' => now(),
            ]);

        if ($expired > 0) {
            Log::info('[ROOM-RESERVATIONS] Expired holds released', ['count' => $expired]);
            $this->info("Expired {$expired} room reservation(s).");
        }

        return self::SUCCESS;
    }
}
