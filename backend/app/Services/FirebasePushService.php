<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\PushNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebasePushService
{
    public function send(DeviceToken $deviceToken, PushNotification $notification): array
    {
        $projectId = config('services.firebase.project_id');
        $serverKey = config('services.firebase.server_key');

        if (!$projectId && !$serverKey) {
            return [
                'success' => true,
                'message_id' => 'local-' . $notification->id . '-' . $deviceToken->id,
                'simulated' => true,
            ];
        }

        $payload = $this->payload($deviceToken, $notification);

        try {
            $response = $serverKey
                ? Http::withHeaders(['Authorization' => 'key=' . $serverKey])->post('https://fcm.googleapis.com/fcm/send', $payload)
                : Http::withToken($this->accessToken())->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
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
        throw new \RuntimeException('Firebase HTTP v1 requires FIREBASE_SERVER_KEY or a project credential provider.');
    }
}
