<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Models\PushNotificationRecipient;
use App\Jobs\SendPushNotificationJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebasePushService
{
    public function sendToUser(int $userId, string $title, string $body, string $category = 'system', ?string $deepLink = null): void
    {
        $notification = PushNotification::create([
            'title' => $title,
            'body' => $body,
            'category' => $category,
            'status' => PushNotification::STATUS_DRAFT,
            'recipient_count' => 0,
            'deep_link' => $deepLink,
        ]);

        $tokens = DeviceToken::where('user_id', $userId)->where('is_active', true)->get();
        if ($tokens->isEmpty()) {
            PushNotificationRecipient::create([
                'push_notification_id' => $notification->id,
                'user_id' => $userId,
                'device_token_id' => null,
                'status' => 'no_active_token',
                'error_message' => 'No active device token',
            ]);
            $notification->update([
                'status' => PushNotification::STATUS_SENT,
                'recipient_count' => 1,
                'error_count' => 1,
                'sent_at' => now(),
            ]);
            Cache::forget("push:unread:{$userId}");
            return;
        }

        foreach ($tokens as $token) {
            PushNotificationRecipient::create([
                'push_notification_id' => $notification->id,
                'user_id' => $userId,
                'device_token_id' => $token->id,
                'status' => 'pending',
            ]);
        }

        $notification->update([
            'status' => PushNotification::STATUS_SCHEDULED,
            'recipient_count' => $tokens->count(),
        ]);

        SendPushNotificationJob::dispatch($notification->id);
    }

    public function send(DeviceToken $deviceToken, PushNotification $notification): array
    {
        $projectId = config('services.firebase.project_id');
        $serverKey = config('services.firebase.server_key');
        $credentials = config('services.firebase.credentials');

        if (!$projectId && $credentials) {
            $projectId = $this->credentials()['project_id'] ?? null;
        }

        if (!$serverKey && (!$projectId || !$credentials)) {
            if (!config('services.firebase.allow_simulated')) {
                Log::error('Firebase push is not configured', [
                    'notification_id' => $notification->id,
                    'device_token_id' => $deviceToken->id,
                ]);

                return [
                    'success' => false,
                    'error' => 'Firebase push is not configured. Set FIREBASE_SERVER_KEY or FIREBASE_PROJECT_ID/FIREBASE_CREDENTIALS.',
                ];
            }

            return [
                'success' => true,
                'message_id' => 'local-' . $notification->id . '-' . $deviceToken->id,
                'simulated' => true,
            ];
        }

        $payload = $this->payload($deviceToken, $notification);

        try {
            $response = $serverKey
                ? Http::withHeaders(['Authorization' => 'key=' . $serverKey])
                    ->post('https://fcm.googleapis.com/fcm/send', $payload)
                : Http::withToken($this->accessToken())
                    ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                        'message' => $payload['message'],
                    ]);
        } catch (\Throwable $e) {
            Log::error('FCM request failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if ($response->successful()) {
            return [
                'success' => true,
                'message_id' => data_get($response->json(), 'name') ?? data_get($response->json(), 'results.0.message_id'),
            ];
        }

        $body = $response->json() ?: ['body' => $response->body()];
        $error = data_get($body, 'error.message') ?? data_get($body, 'results.0.error') ?? 'FCM error';

        if (in_array($error, ['NotRegistered', 'InvalidRegistration', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            $deviceToken->update(['is_active' => false]);
        }

        return ['success' => false, 'error' => $error, 'response' => $body];
    }

    private function payload(DeviceToken $deviceToken, PushNotification $notification): array
    {
        $data = [
            'notification_id' => (string) $notification->id,
            'title' => $notification->title,
            'body' => $notification->body,
            'category' => $notification->category,
            'image_url' => (string) $notification->image_url,
            'deep_link' => (string) $notification->deep_link,
            'created_at' => optional($notification->created_at)->toIso8601String(),
        ];

        if (config('services.firebase.server_key')) {
            return [
                'to' => $deviceToken->token,
                'priority' => $notification->priority === 'high' ? 'high' : 'normal',
                'notification' => array_filter([
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'image' => $notification->image_url,
                    'sound' => 'default',
                ]),
                'data' => $data,
            ];
        }

        return [
            'message' => [
                'token' => $deviceToken->token,
                'notification' => array_filter([
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'image' => $notification->image_url,
                ]),
                'data' => $data,
                'android' => [
                    'priority' => $notification->priority === 'high' ? 'HIGH' : 'NORMAL',
                    'notification' => [
                        'channel_id' => 'eds_high_importance',
                        'sound' => 'default',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'mutable-content' => $notification->image_url ? 1 : 0,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function accessToken(): string
    {
        $cacheKey = 'firebase_http_v1_access_token';
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $credentials = $this->credentials();
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $unsignedJwt = $this->base64Url(json_encode($header))
            . '.'
            . $this->base64Url(json_encode($claims));
        $signed = openssl_sign(
            $unsignedJwt,
            $signature,
            $credentials['private_key'],
            'sha256WithRSAEncryption'
        );
        if (!$signed) {
            throw new \RuntimeException('Unable to sign Firebase service account JWT.');
        }

        $assertion = $unsignedJwt . '.' . $this->base64Url($signature);
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Unable to obtain Firebase access token: ' . $response->body());
        }

        $token = (string) data_get($response->json(), 'access_token');
        $ttl = max(60, ((int) data_get($response->json(), 'expires_in', 3600)) - 60);
        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        return $token;
    }

    private function credentials(): array
    {
        $credentials = config('services.firebase.credentials');
        if (!$credentials) {
            throw new \RuntimeException('FIREBASE_CREDENTIALS is not configured.');
        }

        $path = $credentials;
        if (!is_file($path) && function_exists('base_path')) {
            $path = base_path($credentials);
        }

        $json = is_file($path) ? file_get_contents($path) : $credentials;
        $data = json_decode((string) $json, true);

        if (!is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            throw new \RuntimeException('FIREBASE_CREDENTIALS must be a service account JSON file path or JSON string.');
        }

        return $data;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
