<?php

namespace App\Services\Order;

/**
 * Buduje obiekt `form`, którego CRM /Orders/createOrder oczekuje dla obozu
 * i półkolonii (analogicznie do portalu: courseAgeRangesDVID, courseDanceStyleDVID,
 * diet, departure, locationsschool, discription, tshirtSize).
 *
 * Źródła pól (priorytetowo):
 *  1. payload['form']      — jeśli Flutter wysyła już gotowe DVID-y,
 *  2. payload['campForm']  — pełny snapshot formularza z kreatora Fluttera,
 *  3. payload['rawCourseData'] / ['rawOfferData'] — wymiary samego obozu
 *     (ageRangeId, styleId) jako fallback, gdy użytkownik nie wybiera ich ręcznie.
 */
final class CampOrderFormBuilder
{
    /**
     * @param  array  $payload        Zwalidowany payload z Fluttera.
     * @param  bool   $isDayCamp      Czy to półkolonia (dodaje zgody).
     * @return array
     */
    public static function build(array $payload, bool $isDayCamp = false): array
    {
        $form     = is_array($payload['form'] ?? null) ? $payload['form'] : [];
        $campForm = is_array($payload['campForm'] ?? null) ? $payload['campForm'] : [];
        $raw      = is_array($payload['rawCourseData'] ?? null)
            ? $payload['rawCourseData']
            : (is_array($payload['rawOfferData'] ?? null) ? $payload['rawOfferData'] : []);

        $ageDvid = (int) (
            $form['courseAgeRangesDVID']
            ?? $campForm['courseAgeRangesDVID']
            ?? $raw['ageRangeId']
            ?? $raw['ageRangeID']
            ?? 0
        );

        $styleDvid = (int) (
            $form['courseDanceStyleDVID']
            ?? $campForm['courseDanceStyleDVID']
            ?? $raw['styleId']
            ?? $raw['styleID']
            ?? 0
        );

        $dietDvid = (int) (
            $form['diet']
            ?? $campForm['dietDVID']
            ?? $campForm['diet']
            ?? 0
        );

        $departure = (int) (
            $form['departure']
            ?? $campForm['departureDVID']
            ?? 0
        );

        $locationsSchool = (int) (
            $form['locationsschool']
            ?? $campForm['locationsschool']
            ?? 0
        );

        $description = (string) (
            $form['discription']
            ?? $campForm['notes']
            ?? $campForm['discription']
            ?? ''
        );

        $tshirtSize = (string) (
            $form['tshirtSize']
            ?? $campForm['tshirtSize']
            ?? ''
        );

        $result = [
            'courseAgeRangesDVID'  => $ageDvid,
            'courseDanceStyleDVID' => $styleDvid,
            'diet'                 => $dietDvid,
            'departure'            => $departure,
            'locationsschool'      => $locationsSchool,
            'discription'          => $description,
            'tshirtSize'           => $tshirtSize,
        ];

        if ($isDayCamp) {
            $result['consentReceiveSmsEmailPhone'] = self::boolInt($campForm['consentReceiveSmsEmailPhone'] ?? $campForm['consentTutloPhone'] ?? false);
            $result['marketingAgreement']          = self::boolInt($campForm['consentMarketing'] ?? false);
            $result['tutloPhone']                  = self::boolInt($campForm['consentTutloPhone'] ?? false);
            $result['tutloEmail']                  = self::boolInt($campForm['consentTutloEmail'] ?? false);
            $result['kartaKwalifikacyjna']         = self::boolInt($campForm['consentKartaKwalifikacyjna'] ?? false);
        }

        return $result;
    }

    private static function boolInt($value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }
}
