<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Rzucany wewnątrz transakcji tworzenia rezerwacji, gdy slot sali okazał się
 * niedostępny przy ponownej walidacji (kolizja z CRM lub innym holdem, limit
 * współdzielenia). Wiadomość trafia do klienta jako 409.
 */
class RoomUnavailableException extends RuntimeException
{
}
