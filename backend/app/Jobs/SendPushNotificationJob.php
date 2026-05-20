<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Services\FirebasePushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $pushNotificationId;

    public function __construct(int $pushNotificationId)
    {
        $this->pushNotificationId = $pushNotificationId;
    }

    public function handle(FirebasePushService $pushService): void
    {
        $notification = PushNotification::findOrFail($this->pushNotificationId);

        if (in_array($notification->status, [PushNotification::STATUS_SENT, PushNotification::STATUS_CANCELLED], true)) {
            return;
        }

        $notification->update(['status' => PushNotification::STATUS_SENDING]);

        $sent = 0;
        $errors = 0;

        $notification->recipients()
            ->where('status', 'pending')
            ->with('deviceToken')
            ->chunkById(200, function ($recipients) use ($pushService, $notification, &$sent, &$errors) {
                foreach ($recipients as $recipient) {
                    $deviceToken = $recipient->deviceToken;

                    if (!$deviceToken || !$deviceToken->is_active) {
                        $recipient->update(['status' => 'no_active_token', 'error_message' => 'No active device token']);
                        $errors++;
                        continue;
                    }

                    $result = $pushService->send($deviceToken, $notification);

                    if ($result['success'] ?? false) {
                        $recipient->update([
                            'status' => 'sent',
                            'provider_message_id' => $result['message_id'] ?? null,
                            'sent_at' => now(),
                        ]);
                        $sent++;
                    } else {
                        $recipient->update([
                            'status' => 'failed',
                            'error_message' => $result['error'] ?? 'Push provider error',
                        ]);
                        $errors++;
                    }
                }
            });

        DB::transaction(function () use ($notification, $sent, $errors) {
            $notification->refresh();
            $notification->update([
                'status' => $errors > 0 && $sent === 0 ? PushNotification::STATUS_FAILED : PushNotification::STATUS_SENT,
                'sent_at' => now(),
                'sent_count' => $notification->sent_count + $sent,
                'error_count' => $notification->error_count + $errors,
            ]);
        });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendPushNotificationJob failed', [
            'notification_id' => $this->pushNotificationId,
            'error' => $exception->getMessage(),
        ]);

        PushNotification::whereKey($this->pushNotificationId)->update([
            'status' => PushNotification::STATUS_FAILED,
        ]);
    }
}
