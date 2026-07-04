<?php

namespace App\Services\Order;

/**
 * Builds a CRM-compatible order payload for Ticket offers.
 * Extends the base builder with ticket-specific fields:
 * eventID, ticketType.
 */
final class CrmTicketOrderPayloadBuilder
{
    public static function build(
        array   $payload,
        string  $guid,
        int     $authCrmUserId,
        int     $defaultLocalizationsId = 0,
        ?int    $participantUsersId = null
    ): array {
        $base = CrmOrderPayloadBuilder::build(
            $payload,
            $guid,
            $authCrmUserId,
            $defaultLocalizationsId,
            $participantUsersId
        );

        // Fallback na dane oferty: API zwraca klucz 'eventId' (małe d),
        // starsze wersje apki wysyłały eventID=0.
        $eventId = (int) ($payload['eventID'] ?? $payload['eventId'] ?? 0);
        if ($eventId === 0) {
            $raw = (array) ($payload['rawCourseData'] ?? []);
            $eventId = (int) (
                $raw['eventID'] ?? $raw['eventId'] ?? $raw['eventsTicketsID'] ?? $raw['id'] ?? 0
            );
        }

        return array_merge($base, [
            'eventID'    => $eventId,
            'ticketType' => (string) ($payload['ticketType'] ?? ''),
        ]);
    }
}
