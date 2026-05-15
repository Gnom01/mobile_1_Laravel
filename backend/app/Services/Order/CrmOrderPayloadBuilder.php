<?php

namespace App\Services\Order;

/**
 * Builds the CRM-compatible request body from the validated Flutter payload.
 *
 * Flutter sends a mobile-friendly structure; CRM expects a specific flat body
 * with allInstallments, arrayOfSelectedInstallments, usersID, payer_UsersID, etc.
 *
 * This builder handles both:
 *  - New format: allInstallments + arrayOfSelectedInstallments at root level
 *  - Legacy format: rawSelectedPricing.paymentShedule + installments
 */
final class CrmOrderPayloadBuilder
{
    /**
     * @param  array    $payload              Validated Flutter request data (stored in payload_json)
     * @param  string   $guid                Order GUID (from CreateOrderData, not from payload)
     * @param  int      $authCrmUserId       Authenticated user's CRM UsersID (payer / logged-in parent)
     * @param  int      $defaultLocalizationsId
     * @param  int|null $participantUsersId  CRM UsersID of the course participant (resolved from GUID)
     * @return array                         CRM body ready to wrap in {"type":"Order","body":{...}}
     */
    public static function build(array $payload, string $guid, int $authCrmUserId, int $defaultLocalizationsId = 0, ?int $participantUsersId = null): array
    {
        $allInstallments = self::buildInstallments($payload);

        // Participant: use the explicitly resolved ID; fall back to authenticated user only when
        // no participant was specified (e.g. the user orders for themselves).
        $usersID      = $participantUsersId ?? $authCrmUserId;
        $payerUsersID = (int) ($payload['payer_UsersID'] ?? $payload['payerUserId'] ?? $authCrmUserId);

        $productsID      = (int) ($payload['productsID']      ?? $payload['rawSelectedPricing']['productsID']      ?? 0);
        $coursesHeadingsID = (int) ($payload['coursesHeadingsID'] ?? $payload['rawCourseData']['coursesHeadingsID'] ?? 0);

        $contractAmount    = (float) ($payload['contractAmount']   ?? $payload['allInstallmentsPrice'] ?? 0);
        $contractPeriodFrom = (string) ($payload['contractPeriodFrom'] ?? $payload['contractStartDate'] ?? '');
        $dataTo            = (string) ($payload['dataTo']            ?? $payload['contractEndDate']    ?? '');

        $arrayOfSelected = self::resolveArrayOfSelectedInstallments($payload, $allInstallments);

        return [
            'guid'                        => $guid,
            'allInstallments'             => $allInstallments,
            'paymentMethodsDVID'          => (int)    ($payload['paymentMethodsDVID']   ?? 5),
            'paymentMethodsP24'           => (string) ($payload['paymentMethodsP24']    ?? 'p24'),
            'paymentCardID'               => (string) ($payload['paymentCardID']         ?? ''),
            'buyerNIP'                    => $payload['buyerNIP'] ?? null,
            'purchaseKey'                 => (string) ($payload['purchaseKey']           ?? ''),
            'returnUrl'                   => (string) ($payload['returnUrl']             ?? ''),
            'current_LocalizationsID'     => (int) ($payload['current_LocalizationsID'] ?? $defaultLocalizationsId),
            'contractPeriodFrom'          => $contractPeriodFrom,
            'usersID'                     => $usersID,
            'payer_UsersID'               => $payerUsersID,
            'productsID'                  => $productsID,
            'coursesHeadingsID'           => $coursesHeadingsID,
            'contractAmount'              => $contractAmount,
            'paymentStatusesDVID'         => (int)    ($payload['paymentStatusesDVID']  ?? 1),
            'arrayOfSelectedInstallments' => $arrayOfSelected,
            'entryFee'                    => (float)  ($payload['entryFee']             ?? 0),
            'promotionsSalesIDList'       => (string) ($payload['promotionsSalesIDList'] ?? '0'),
            'dataTo'                      => $dataTo,
        ];
    }

    // ─── Private helpers ───────────────────────────────────────────────────────

    private static function buildInstallments(array $payload): array
    {
        // Prefer top-level allInstallments (new Flutter format)
        // Fall back to rawSelectedPricing.paymentShedule (legacy format)
        $raw = $payload['allInstallments']
            ?? $payload['rawSelectedPricing']['paymentShedule']
            ?? [];

        if (empty($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $inst) {
            $inst       = (array) $inst;
            $price      = (float) ($inst['paymentPositionPrice']         ?? 0);
            $priceDisc  = (float) ($inst['paymentPositionPriceDiscount'] ?? $price);

            $result[] = [
                'countNumber'                  => (int)    ($inst['countNumber']           ?? 0),
                'paymentDate'                  => (string) ($inst['paymentDate']           ?? ''),
                'paymentPositionPrice'         => $price,
                'isFullUnitOfAccount'          => (int)    ($inst['isFullUnitOfAccount']   ?? 0),
                'isVoid'                       => (int)    ($inst['isVoid']                ?? 0),
                'periodFromDate'               => (string) ($inst['periodFromDate']        ?? ''),
                'periodToDate'                 => (string) ($inst['periodToDate']          ?? ''),
                'discountCash'                 => (float)  ($inst['discountCash']          ?? 0),
                'discountProcent'              => (float)  ($inst['discountProcent']       ?? 0),
                'discountValue'                => $inst['discountValue']              ?? [],
                'discountFromDate'             => $inst['discountFromDate']           ?? [],
                'discountToDate'               => $inst['discountToDate']             ?? [],
                'numberOfDiscountMinutes'      => $inst['numberOfDiscountMinutes']    ?? [],
                'numberOfDiscountLessons'      => $inst['numberOfDiscountLessons']    ?? [],
                'discountAmountPerMinute'      => $inst['discountAmountPerMinute']    ?? [],
                'discountAmountPerLessons'     => $inst['discountAmountPerLessons']   ?? [],
                'paymentPositionPriceDiscount' => $priceDisc,
            ];
        }

        return $result;
    }

    /**
     * Resolve the arrayOfSelectedInstallments string (e.g. "9" or "9,10").
     *
     * Priority:
     * 1. Explicit value from payload (new format)
     * 2. Derive from payload['installments'] – match by date+price to get countNumbers
     * 3. Fall back to all countNumbers in allInstallments
     */
    private static function resolveArrayOfSelectedInstallments(array $payload, array $allInstallments): string
    {
        // 1. Explicit
        if (!empty($payload['arrayOfSelectedInstallments'])) {
            return (string) $payload['arrayOfSelectedInstallments'];
        }

        // 2. Derive from selected installments in old format
        if (!empty($payload['installments']) && !empty($allInstallments)) {
            $selectedNums = [];
            foreach ($payload['installments'] as $sel) {
                $sel = (array) $sel;
                $selDate  = $sel['paymentDate'] ?? '';
                $selPrice = (float) ($sel['paymentPositionPrice'] ?? $sel['amount'] ?? 0);

                foreach ($allInstallments as $inst) {
                    if (
                        $inst['paymentDate'] === $selDate
                        && abs($inst['paymentPositionPrice'] - $selPrice) < 0.01
                    ) {
                        $selectedNums[] = $inst['countNumber'];
                        break;
                    }
                }
            }

            if (!empty($selectedNums)) {
                return implode(',', $selectedNums);
            }
        }

        // 3. All installments
        $all = array_column($allInstallments, 'countNumber');
        return implode(',', $all);
    }
}