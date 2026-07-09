<?php

return [
    // Maksymalna liczba równoległych rezerwacji współdzielonych na ten sam
    // slot sali. Domyślnie 5 (zakres 4–5 wg wymagań).
    'shared_max' => (int) env('ROOM_RESERVATION_SHARED_MAX', 5),

    // Czas życia tymczasowej rezerwacji (hold) w minutach.
    'hold_minutes' => (int) env('ROOM_RESERVATION_HOLD_MINUTES', 15),

    // Próg wieku dziecko/dorosły (blokada mieszania w jednej rezerwacji).
    'child_max_age' => (int) env('ROOM_RESERVATION_CHILD_MAX_AGE', 18),
];
