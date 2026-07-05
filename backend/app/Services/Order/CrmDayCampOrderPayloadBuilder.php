<?php

namespace App\Services\Order;

/**
 * Builds a CRM-compatible order payload for Day Camp (półkolonie) offers.
 * Extends the base builder with the same camp-specific fields as CrmCampOrderPayloadBuilder.
 */
final class CrmDayCampOrderPayloadBuilder
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

        $base = CampInstallmentsFallback::apply($base);

        return array_merge($base, [
            // Portal (dayCamp): orderType OrderCamp, current_LocalizationsID -1,
            // contractPeriodFrom = data złożenia zamówienia (nie start turnusu),
            // dataTo puste. price_LocalizationsID przekazujemy, gdy klient poda.
            'orderType'               => 'OrderCamp',
            'current_LocalizationsID' => -1,
            'price_LocalizationsID'   => (int) ($payload['price_LocalizationsID'] ?? 0),
            'contractPeriodFrom'      => now()->toIso8601String(),
            'dataTo'                  => '',
            'turnusName'        => (string) ($payload['turnusName']        ?? ''),
            'departurePlace'    => (string) ($payload['departurePlace']    ?? ''),
            'transportOptions'  => $payload['transportOptions']  ?? [],
            'dietOptions'       => $payload['dietOptions']       ?? [],
            'medicalRequired'   => (int) ($payload['medicalRequired']  ?? 0),
            'guardianRequired'  => (int) ($payload['guardianRequired'] ?? 0),
            // Obiekt `form` wymagany przez CRM /Orders/createOrder dla półkolonii
            // (z dodatkowymi zgodami: marketing, Tutlo, karta kwalifikacyjna).
            'form'              => CampOrderFormBuilder::build($payload, true),
            // Pełne dane kwalifikacyjne uczestnika — jak portal (campForm).
            'campForm'          => (array) ($payload['campForm'] ?? []),
        ]);
    }
}
