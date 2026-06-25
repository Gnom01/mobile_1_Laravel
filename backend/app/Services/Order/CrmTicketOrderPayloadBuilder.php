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

        return array_merge($base, [
            'eventID'    => (int)    ($payload['eventID']    ?? $payload['eventId']    ?? 0),
            'ticketType' => (string) ($payload['ticketType'] ?? ''),
        ]);
    }
}
