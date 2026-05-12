<?php

namespace App\Services;

use App\Models\CrmUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CrmPortalCheckoutService
{
    public function startSchedulePayment(
        CrmUser $payer,
        Collection $scheduleItems,
        string $returnUrl,
        int $paymentMethod = 5,
        ?string $buyerNip = null
    ): array {
        $localizationId = (int) $scheduleItems->pluck('localizationsID')->filter()->first();
        $scheduleParam = $scheduleItems
            ->map(fn ($item) => implode('/', [
                (int) $item->localizationsID,
                (int) $item->usersID,
                (int) $item->usersPaymentsSchedulesID,
            ]))
            ->implode(',');

        $payload = [
            'keyController' => 'onLineSchedulePayment',
            'current_LocalizationsID' => $localizationId,
            'paymentMethodsDVID' => $paymentMethod,
            'usersPaymentsSchedulesID' => $scheduleParam,
            'usersID' => (int) $payer->UsersID,
            'NIP' => $buyerNip ?? '',
            'urlLink' => $returnUrl,
        ];

        $response = Http::baseUrl(config('services.crm.portal_base_url'))
            ->withOptions([
                'verify' => (bool) config('services.crm.verify_tls', true),
            ])
            ->timeout(30)
            ->post(config('services.crm.portal_orders_endpoint'), $payload)
            ->throw()
            ->json();

        $body = $response['body'] ?? $response;
        $token = $body['token'] ?? $body['html'] ?? null;
        $sessionId = $body['sessionID'] ?? $body['sessionId'] ?? null;
        $redirectUrl = $body['redirect_url'] ?? $body['payment_url'] ?? $body['url'] ?? null;

        if (!$redirectUrl && $token) {
            $template = config('services.crm.payment_token_url_template');
            if (is_string($template) && $template !== '') {
                $redirectUrl = Str::replace('{token}', $token, $template);
            }
        }

        return [
            'raw' => $response,
            'body' => $body,
            'crm_session_id' => $sessionId,
            'crm_payment_token' => $token,
            'redirect_url' => $redirectUrl,
        ];
    }
}
