<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerwerSmsClient
{
    /**
     * Send an SMS via SerwerSMS API v2.
     *
     * @param string $phoneE164  Phone number in E.164 format (e.g. +48123456789)
     * @param string $message    SMS text content
     * @param bool   $test       If true, API simulates sending (no actual SMS)
     * @return array             Response with status, ok flag, parsed data, and raw body
     */
    public function sendOtp(string $phoneE164, string $message, bool $test = false): array
    {
        $token  = config('services.serwersms.token');
        $sender = config('services.serwersms.sender');

        $payload = [
            'phone'   => $phoneE164,
            'text'    => $message,
            'details' => true,
            'utf'     => true,
            'flash'   => false,
            'test'    => false,
        ];

        if (!empty($sender)) {
            $payload['sender'] = $sender;
        }

        $resp = Http::withOptions([
            'verify' => app()->environment('local') ? false : true,
        ])->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->asForm()->post('https://api2.serwersms.pl/messages/send_sms.json', $payload);

        $result = [
            'status' => $resp->status(),
            'ok'     => $resp->ok(),
            'data'   => $resp->json(),
            'raw'    => $resp->body(),
        ];

        Log::info('SerwerSMS response', [
            'phone'  => $phoneE164,
            'status' => $result['status'],
            'ok'     => $result['ok'],
            'data'   => $result['data'],
        ]);

        return $result;
    }
}
