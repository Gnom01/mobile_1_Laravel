<?php

namespace App\Services\Order;

/**
 * Portalowy fallback rat dla obozów/półkolonii: gdy wybrany wariant cennika
 * nie ma harmonogramu (paymentShedule puste), portal syntetyzuje JEDNĄ ratę
 * {countNumber: 1, paymentPositionPrice: cena, paymentDate: dziś}. Bez tego
 * CRM dostawał allInstallments=[] i pusty arrayOfSelectedInstallments.
 */
final class CampInstallmentsFallback
{
    public static function apply(array $crmBody): array
    {
        if (!empty($crmBody['allInstallments'])) {
            return $crmBody;
        }

        $amount = (float) ($crmBody['contractAmount'] ?? 0);
        if ($amount <= 0) {
            return $crmBody;
        }

        $today = now()->toDateString();

        $crmBody['allInstallments'] = [[
            'countNumber'                  => 1,
            'paymentDate'                  => $today,
            'paymentPositionPrice'         => $amount,
            'isFullUnitOfAccount'          => 1,
            'isVoid'                       => 0,
            'periodFromDate'               => $today,
            'periodToDate'                 => $today,
            'discountCash'                 => 0.0,
            'discountProcent'              => 0.0,
            'discountValue'                => [],
            'discountFromDate'             => [],
            'discountToDate'               => [],
            'numberOfDiscountMinutes'      => [],
            'numberOfDiscountLessons'      => [],
            'discountAmountPerMinute'      => [],
            'discountAmountPerLessons'     => [],
            'paymentPositionPriceDiscount' => $amount,
        ]];
        $crmBody['arrayOfSelectedInstallments'] = '1';

        return $crmBody;
    }
}
