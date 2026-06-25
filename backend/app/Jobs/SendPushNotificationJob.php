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
use Illuminate\Support\Facades\Cache;
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

        $totalRecipients = $notification->recipients()->count();
        if ($totalRecipients === 0) {
            Log::warning('Push notification has no recipients', [
                'notification_id' => $notification->id,
            ]);

            $notification->update([
                'status' => PushNotification::STATUS_FAILED,
                'sent_at' => now(),
            ]);

            return;
        }

        $usersToRefresh = [];

        $notification->recipients()
            ->where('status', 'pending')
            ->with('deviceToken')
            ->chunkById(200, function ($recipients) use ($pushService, $notification, &$usersToRefresh) {
                foreach ($recipients as $recipient) {
                    $usersToRefresh[(int) $recipient->user_id] = true;
                    $deviceToken = $recipient->deviceToken;

                    if (!$deviceToken || !$deviceToken->is_active) {
                        $recipient->update(['status' => 'no_active_token', 'error_message' => 'No active device token']);
                        continue;
                    }

                    $result = $pushService->send($deviceToken, $notification);

                    if ($result['success'] ?? false) {
                        $recipient->update([
                            'status' => 'sent',
                            'provider_message_id' => $result['message_id'] ?? null,
                            'sent_at' => now(),
                        ]);
                    } else {
                        $recipient->update([
                            'status' => 'failed',
                            'error_message' => $result['error'] ?? 'Push provider error',
                        ]);
                    }
                }
            });

        $notification->recipients()
            ->where('status', 'no_active_token')
            ->pluck('user_id')
            ->each(function ($userId) use (&$usersToRefresh) {
                $usersToRefresh[(int) $userId] = true;
            });

        DB::transaction(function () use ($notification) {
            $notification->refresh();
            $sent = $notification->recipients()->where('status', 'sent')->count();
            $errors = $notification->recipients()->whereIn('status', ['failed', 'no_active_token'])->count();

            $notification->update([
                'status' => PushNotification::STATUS_SENT,
                'sent_at' => now(),
                'sent_count' => $sent,
                'error_count' => $errors,
            ]);
        });

        foreach (array_keys($usersToRefresh) as $userId) {
            Cache::forget("push:unread:{$userId}");
        }

        Log::info('Push notification send finished', [
            'notification_id' => $notification->id,
            'recipients_count' => $totalRecipients,
            'sent_count' => $notification->fresh()->sent_count,
            'error_count' => $notification->fresh()->error_count,
        ]);
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
