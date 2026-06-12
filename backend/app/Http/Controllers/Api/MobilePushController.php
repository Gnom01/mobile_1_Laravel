<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterDeviceTokenRequest;
use App\Http\Resources\PushNotificationResource;
use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Models\PushNotificationRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MobilePushController extends Controller
{
    public function registerDeviceToken(RegisterDeviceTokenRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();
        $hash = DeviceToken::hashToken($data['token']);

        $token = DeviceToken::updateOrCreate(
            ['token_hash' => $hash],
            [
                'user_id' => $user->UsersID,
                'platform' => $data['platform'],
                'token' => $data['token'],
                'device_id' => $data['device_id'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'locale' => $data['locale'] ?? null,
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );

        $this->forgetUnread($user->UsersID);

        return response()->json(['success' => true, 'device_token_id' => $token->id]);
    }

    public function deleteDeviceToken(Request $request, string $token)
    {
        $hash = DeviceToken::hashToken($token);
        DeviceToken::where('user_id', $request->user()->UsersID)
            ->where('token_hash', $hash)
            ->update(['is_active' => false]);

        return response()->json(['success' => true]);
    }

    public function index(Request $request)
    {
        $userId = $request->user()->UsersID;
        $category = $request->query('category');
        $perPage = min((int) $request->query('per_page', 20), 100);

        $query = PushNotification::query()
            ->whereHas('recipients', fn ($q) => $q->where('user_id', $userId))
            ->with(['recipients' => fn ($q) => $q->where('user_id', $userId)->limit(1)])
            ->whereIn('status', [PushNotification::STATUS_SENT, PushNotification::STATUS_SENDING])
            ->latest('created_at');

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        return PushNotificationResource::collection($query->paginate($perPage));
    }

    public function unreadCount(Request $request)
    {
        $userId = $request->user()->UsersID;
        $count = Cache::remember($this->unreadKey($userId), now()->addMinutes(5), function () use ($userId) {
            return PushNotificationRecipient::where('user_id', $userId)
                ->whereNull('read_at')
                ->whereHas('notification', fn ($q) => $q->whereIn('status', [PushNotification::STATUS_SENT, PushNotification::STATUS_SENDING]))
                ->distinct('push_notification_id')
                ->count('push_notification_id');
        });

        return response()->json(['success' => true, 'unread_count' => $count]);
    }

    public function show(Request $request, int $id)
    {
        $userId = $request->user()->UsersID;
        $notification = PushNotification::query()
            ->whereKey($id)
            ->whereHas('recipients', fn ($q) => $q->where('user_id', $userId))
            ->with(['recipients' => fn ($q) => $q->where('user_id', $userId)->limit(1)])
            ->firstOrFail();

        return new PushNotificationResource($notification);
    }

    public function markRead(Request $request, int $id)
    {
        $this->recipients($request, $id)->update(['read_at' => now()]);
        $this->forgetUnread($request->user()->UsersID);

        return response()->json(['success' => true]);
    }

    public function markOpened(Request $request, int $id)
    {
        $this->recipients($request, $id)->update(['opened_at' => now(), 'read_at' => now()]);
        $this->forgetUnread($request->user()->UsersID);

        return response()->json(['success' => true]);
    }

    public function readAll(Request $request)
    {
        PushNotificationRecipient::where('user_id', $request->user()->UsersID)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->forgetUnread($request->user()->UsersID);

        return response()->json(['success' => true]);
    }

    private function recipient(Request $request, int $notificationId): PushNotificationRecipient
    {
        return PushNotificationRecipient::where('user_id', $request->user()->UsersID)
            ->where('push_notification_id', $notificationId)
            ->firstOrFail();
    }

    private function recipients(Request $request, int $notificationId)
    {
        $this->recipient($request, $notificationId);

        return PushNotificationRecipient::where('user_id', $request->user()->UsersID)
            ->where('push_notification_id', $notificationId);
    }

    private function unreadKey(int $userId): string
    {
        return "push:unread:{$userId}";
    }

    private function forgetUnread(int $userId): void
    {
        Cache::forget($this->unreadKey($userId));
    }
}
