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
        $testMode = $test || (bool) config('services.sms.test_mode', false);

        $payload = [
            'phone'   => $phoneE164,
            'text'    => $message,
            'details' => true,
            'utf'     => true,
            'flash'   => false,
            'test'    => $testMode,
        ];

        if (!empty($sender)) {
            $payload['sender'] = $sender;
        }

        $resp = Http::withOptions([
            'verify' => !app()->environment(['local', 'testing']),
        ])->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->asForm()->post('https://api2.serwersms.pl/messages/send_sms.json', $payload);

        $result = [
            'status' => $resp->status(),
            'ok'     => $resp->ok(),
            'data'   => $resp->json(),
            'raw'    => $resp->body(),
        ];

        $logContext = [
            'phone_suffix' => substr($phoneE164, -3),
            'test_mode' => $testMode,
            'status' => $result['status'],
            'ok'     => $result['ok'],
            'success' => data_get($result['data'], 'success'),
            'queued' => data_get($result['data'], 'queued'),
            'unsent' => data_get($result['data'], 'unsent'),
            'items' => $this->summarizeItems(data_get($result['data'], 'items', [])),
            'provider_response' => $this->sanitizeProviderResponse($result['data'] ?? $result['raw']),
        ];

        Log::info('SerwerSMS response details', $logContext);

        if (!$result['ok'] || data_get($result['data'], 'success') === false || (int) data_get($result['data'], 'unsent', 0) > 0) {
            Log::warning('SerwerSMS send problem', $logContext);
        }

        return $result;
    }

    /**
     * Keep the operator response useful for debugging without logging OTP codes
     * or full phone numbers.
     */
    private function sanitizeProviderResponse(mixed $response): mixed
    {
        if (is_array($response)) {
            $sanitized = [];

            foreach ($response as $key => $value) {
                $normalizedKey = strtolower((string) $key);

                if (in_array($normalizedKey, ['text', 'token', 'authorization'], true)) {
                    $sanitized[$key] = '[redacted]';
                    continue;
                }

                if ($normalizedKey === 'phone' && is_string($value)) {
                    $sanitized[$key] = '[redacted:' . substr($value, -3) . ']';
                    continue;
                }

                $sanitized[$key] = $this->sanitizeProviderResponse($value);
            }

            return $sanitized;
        }

        if (is_string($response)) {
            $response = preg_replace('/\b\d{6}\b/', '[otp-redacted]', $response) ?? $response;
            $response = preg_replace('/(?:\+?48)?\d{9}\b/', '[phone-redacted]', $response) ?? $response;

            return substr($response, 0, 2000);
        }

        return $response;
    }

    private function summarizeItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        return array_map(static function (mixed $item): array {
            if (!is_array($item)) {
                return [
                    'raw_item_type' => gettype($item),
                ];
            }

            return [
                'id' => $item['id'] ?? null,
                'phone_suffix' => isset($item['phone']) ? substr((string) $item['phone'], -3) : null,
                'status' => $item['status'] ?? null,
                'queued' => $item['queued'] ?? null,
                'parts' => $item['parts'] ?? null,
                'stat_id' => $item['stat_id'] ?? null,
                'error_code' => $item['error_code'] ?? null,
                'error' => $item['error'] ?? null,
            ];
        }, $items);
    }
}
