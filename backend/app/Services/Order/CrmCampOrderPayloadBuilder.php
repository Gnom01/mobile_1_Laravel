<?php

namespace App\Services\Order;

/**
 * Builds a CRM-compatible order payload for Camp offers.
 * Extends the base builder with camp-specific fields:
 * turnusName, departurePlace, transportOptions, dietOptions,
 * medicalRequired, guardianRequired.
 */
final class CrmCampOrderPayloadBuilder
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
            'turnusName'        => (string) ($payload['turnusName']        ?? ''),
            'departurePlace'    => (string) ($payload['departurePlace']    ?? ''),
            'transportOptions'  => $payload['transportOptions']  ?? [],
            'dietOptions'       => $payload['dietOptions']       ?? [],
            'medicalRequired'   => (int) ($payload['medicalRequired']  ?? 0),
            'guardianRequired'  => (int) ($payload['guardianRequired'] ?? 0),
            // Obiekt `form` wymagany przez CRM /Orders/createOrder dla obozu.
            'form'              => CampOrderFormBuilder::build($payload),
        ]);
    }
}
