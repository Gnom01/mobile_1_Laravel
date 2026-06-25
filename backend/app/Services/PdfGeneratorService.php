<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use DateTime;

class PdfGeneratorService
{
    // EDS logo as base64 data URI (embedded JPEG)
    private const LOGO_DATA_URI = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/4QBYRXhpZgAATU0AKgAAAAgABAExAAIAAAARAAAAPlEQAAEAAAABAQAAAFERAAQAAAABAAAAAFESAAQAAAABAAAAAAAAAABBZG9iZSBJbWFnZVJlYWR5AAD/2wBDAAIBAQIBAQICAgICAgICAwUDAwMDAwYEBAMFBwYHBwcGBwcICQsJCAgKCAcHCg0KCgsMDAwMBwkODw0MDgsMDAz/2wBDAQICAgMDAwYDAwYMCAcIDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAz/wAARCABAAPADASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDz7Sf20Pjvr+q2tjY/Fj4t3t9eyrBb28HifUJJZ5GIVURVlJZmJAAAyScV6D/bn7Zn/P5+05/391z/ABrx39l7xPYeCf2mPh3rWq3MdnpekeJ9Nvby4cHbBDHdRO7nGTgKpPHpX7r/APD2D9nf/oqegf8Afq4/+N18XgKKrxbqVXG3n/wT+5PELOq+RV6NLK8pjiFNNtqm3ytOyXuxe/mfkV/bn7Zn/P5+05/791z/ABr9fPhx+0DD+zR/wTu8I+OPimeaxb3mj+F7KTVv7S3tqd1eGFB5TCUh2uHkO3DkHJJYgAkNsP8Ago...';

    private const MONTH_NAMES = [
        1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień',
        5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień',
        9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień',
    ];

    /**
     * Generate a PDF document.
     *
     * @param string $type  'contract' | 'annex' | 'schedule'
     * @param array  $contractData  Raw contract data from mobile app
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generate(string $type, array $contractData): \Barryvdh\DomPDF\PDF
    {
        $data = $this->prepareData($contractData);
        $data['logo'] = self::LOGO_DATA_URI;

        $view = match ($type) {
            'annex'    => 'pdf.annex',
            'schedule' => 'pdf.schedule',
            default    => 'pdf.contract',
        };

        $pdf = Pdf::loadView($view, ['data' => $data]);
        $pdf->setPaper('a4', 'portrait');
        $pdf->set_option('isRemoteEnabled', false);
        $pdf->set_option('defaultFont', 'DejaVu Sans');

        return $pdf;
    }

    // ─── Data preparation ──────────────────────────────────────────────────────

    private function prepareData(array $raw): array
    {
        $data = [];

        $data['contractSignature'] = $raw['contractSignature'] ?? 'Nr/YZ';
        $data['contractDate']      = $raw['contractDate'] ?? now()->format('d-m-Y');
        $data['contractStartDate'] = $raw['contractStartDate'] ?? '';
        $data['contractEndDate']   = $raw['contractEndDate'] ?? '';
        $data['entryFee']          = (float) ($raw['entryFee'] ?? 0);

        // Selected pricing template – authoritative source for contract financials
        $rawSelectedPricing = $raw['rawSelectedPricing'] ?? [];
        $data['coursePrice']          = (float) ($rawSelectedPricing['amount'] ?? $raw['allInstallmentsPrice'] ?? 0);
        $data['monthlyInstallment']   = (float) ($rawSelectedPricing['unitAmount'] ?? 0);
        $data['numberOfInstallments'] = (int)   ($rawSelectedPricing['numberOfUnitsAccount'] ?? 0);

        // Course / location data
        $courseData = $raw['courseData'] ?? [];
        $data['contractLocation']   = $courseData['clientsCyti'] ?? '';
        $data['companyName']        = $courseData['contractHeader'] ?? '';
        $data['bankAccountNumber']  = $courseData['banckAccountNumber'] ?? '';
        $data['courseData'] = [
            'courseHeadingName' => $courseData['courseHeadingName'] ?? '',
            'courseFrequency'   => $courseData['Frequency'] ?? '',
            'courseDuration'    => $courseData['DurationMin'] ?? '',
        ];

        // Payer
        $payerUser = $raw['payerUser'] ?? [];
        $data['payerUserData'] = [
            'firstName'  => $payerUser['firstName'] ?? '',
            'lastName'   => $payerUser['lastName'] ?? '',
            'postalCode' => $payerUser['postalCode'] ?? '',
            'city'       => $payerUser['city'] ?? '',
            'street'     => $payerUser['street'] ?? '',
            'building'   => $payerUser['building'] ?? '',
            'email'      => $payerUser['email'] ?? '',
            'phone'      => $payerUser['phone'] ?? '',
            'flat'       => $payerUser['flat'] ?? null,
            'pesel'      => $payerUser['pesel'] ?? null,
        ];

        // Participant
        $participantUser = $raw['participantUser'] ?? null;
        if ($participantUser && is_array($participantUser)) {
            $data['participantUser'] = [
                'firstName'   => $participantUser['firstName'] ?? null,
                'lastName'    => $participantUser['lastName'] ?? null,
                'postalCode'  => $participantUser['postalCode'] ?? null,
                'city'        => $participantUser['city'] ?? null,
                'street'      => $participantUser['street'] ?? null,
                'building'    => $participantUser['building'] ?? null,
                'email'       => $participantUser['email'] ?? null,
                'phone'       => $participantUser['phone'] ?? null,
                'flat'        => $participantUser['flat'] ?? null,
                'pesel'       => $participantUser['pesel'] ?? null,
                'dateOfBirth' => $participantUser['dateOfBirdth'] ?? null,
            ];
        } else {
            $data['participantUser'] = null;
        }

        // Group / contract type
        $groupData = $raw['groupData'] ?? [];
        $data['groupData'] = [
            'contractType'    => $this->getReadableContractType($groupData),
            'periodOfPayment' => $this->getReadablePeriodOfPayment($groupData),
        ];

        // Installments – use the full payment schedule from the selected pricing as source of truth
        $paymentSchedule = $rawSelectedPricing['paymentShedule'] ?? $raw['installments'] ?? [];
        $data['installments']    = $this->processInstallmentTableData($paymentSchedule);
        $data['rawInstallments'] = $this->processRawInstallments($paymentSchedule);

        // Pro-rata / entry fee data
        $payZero = $raw['payZero'] ?? [];
        $installmentZero  = (float) ($payZero['installmentZero'] ?? 0);
        $discountCashZero = (float) ($payZero['discountCashZero'] ?? 0);
        $data['payZero'] = [
            'installmentZero'              => $installmentZero,
            'amountZero'                   => (float) ($payZero['amountZero'] ?? 0),
            'discountCashZero'             => $discountCashZero,
            'installmentZeroAfterDiscount' => (float) ($payZero['installmentZeroAfterDiscount'] ?? ($installmentZero - $discountCashZero)),
        ];

        $data['discountName'] = $raw['discountName'] ?? '';

        return $data;
    }

    // ─── Contract type helpers ─────────────────────────────────────────────────

    private function getReadableContractType(array $groupData): string
    {
        $periodsOfValidityDVID = (int) ($groupData['periodsOfValidityDVID'] ?? 0);
        $paymentTypesDVID      = (int) ($groupData['paymentTypesDVID'] ?? 0);
        $paymentDVIDName       = $groupData['paymentDVIDName'] ?? '';

        if ($periodsOfValidityDVID === 1) {
            return $paymentTypesDVID === 1 ? 'Roczna z góry' : 'Roczna ratalna';
        } elseif ($periodsOfValidityDVID === 2) {
            return $paymentTypesDVID === 1 ? 'Semestralna z góry' : 'Semestralna ratalna';
        } elseif ($periodsOfValidityDVID === 3) {
            return 'Miesięczna ' . $paymentDVIDName;
        }
        return '';
    }

    private function getReadablePeriodOfPayment(array $groupData): string
    {
        return ((int) ($groupData['paymentTypesDVID'] ?? 0)) === 1 ? 'Z góry' : 'Ratalna';
    }

    // ─── Compensatory installment detection ───────────────────────────────────

    private function isCompensatoryInstallment(array $pay): bool
    {
        if (isset($pay['discountValue']) && is_array($pay['discountValue'])) {
            return (isset($pay['discountValue'][7])   && (float) $pay['discountValue'][7] > 0)
                || (isset($pay['discountValue']['7']) && (float) $pay['discountValue']['7'] > 0);
        }
        if (isset($pay['discountFromDate']) && is_array($pay['discountFromDate'])) {
            return isset($pay['discountFromDate'][7]) || isset($pay['discountFromDate']['7']);
        }
        return false;
    }

    // ─── Installment table rows for contract ──────────────────────────────────

    private function processInstallmentTableData(array $installmentData): array
    {
        $rows = [];
        $i    = 1;

        foreach ($installmentData as $inst) {
            // Skip void installments
            if ((int) ($inst['isVoid'] ?? 0) === 1) {
                continue;
            }

            $basePrice = (float) ($inst['paymentPositionPrice'] ?? 0);
            if ($basePrice == 0) {
                continue;
            }

            $isCompensatory = $this->isCompensatoryInstallment($inst);
            // Use paymentPositionPriceDiscount as source of truth; derive discount from the difference
            $priceAfterDisc = $isCompensatory
                ? (float) ($inst['paymentPositionPriceDiscount'] ?? $basePrice)
                : $basePrice;
            $discountCash   = $isCompensatory ? round($basePrice - $priceAfterDisc, 2) : 0;

            $month = $this->monthFromDate($inst['periodFromDate'] ?? '');

            $rows[] = [
                'nr'                 => $i,
                'basePrice'          => $this->formatMoney($basePrice),
                'discount'           => $discountCash > 0 ? '- ' . $this->formatMoney($discountCash) : '—',
                'priceAfterDiscount' => $this->formatMoney($priceAfterDisc),
                'paymentDate'        => $inst['paymentDate'] ?? '',
                'month'              => $month,
            ];
            $i++;
        }

        return $rows;
    }

    // ─── Raw installment rows for annex / schedule ────────────────────────────

    private function processRawInstallments(array $installmentData): array
    {
        $rows = [];
        $i    = 1;

        foreach ($installmentData as $inst) {
            // Skip void installments
            if ((int) ($inst['isVoid'] ?? 0) === 1) {
                continue;
            }

            $isCompensatory  = $this->isCompensatoryInstallment($inst);
            $basePrice       = (float) ($inst['paymentPositionPrice'] ?? 0);
            $discountProcent = $isCompensatory ? ($inst['discountProcent'] ?? 0) : 0;
            // Use paymentPositionPriceDiscount as source of truth; derive discount from the difference
            $priceAfterDisc  = $isCompensatory
                ? (float) ($inst['paymentPositionPriceDiscount'] ?? $basePrice)
                : $basePrice;
            $discountCash    = $isCompensatory ? round($basePrice - $priceAfterDisc, 2) : 0;

            $rows[] = [
                'nr'                 => $i,
                'basePrice'          => $this->formatMoney($basePrice),
                'discountProcent'    => $isCompensatory ? $discountProcent . ' %' : '0 %',
                'discountCash'       => $isCompensatory && $discountCash > 0 ? $this->formatMoney($discountCash) : '—',
                'priceAfterDiscount' => $this->formatMoney($priceAfterDisc),
                'paymentMonth'       => $this->monthFromDate($inst['periodFromDate'] ?? ''),
                'paymentDate'        => $inst['paymentDate'] ?? '',
            ];
            $i++;
        }

        return $rows;
    }

    // ─── Formatting helpers ────────────────────────────────────────────────────

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' zł';
    }

    private function monthFromDate(string $dateStr): string
    {
        if (!$dateStr) {
            return '';
        }

        $date = DateTime::createFromFormat('Y-m-d', substr($dateStr, 0, 10));
        if (!$date) {
            return '';
        }

        return self::MONTH_NAMES[(int) $date->format('n')] ?? '';
    }
}
