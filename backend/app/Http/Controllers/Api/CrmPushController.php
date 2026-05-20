<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrmPushNotificationRequest;
use App\Http\Requests\CrmPushPreviewRequest;
use App\Http\Requests\CrmPushTestRequest;
use App\Jobs\SendPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Models\PushNotificationRecipient;
use App\Models\PushSegment;
use App\Services\PushRecipientSegmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrmPushController extends Controller
{
    private PushRecipientSegmentService $segments;

    public function __construct(PushRecipientSegmentService $segments)
    {
        $this->segments = $segments;
    }

    public function previewRecipients(CrmPushPreviewRequest $request)
    {
        $this->authorizeCrm($request);
        $preview = $this->segments->preview($request->input('filters', []), (int) $request->input('limit', 100));

        return response()->json([
            'success' => true,
            'count' => $preview['count'],
            'recipients' => $preview['recipients'],
        ]);
    }

    public function store(CrmPushNotificationRequest $request)
    {
        $this->authorizeCrm($request);
        $data = $request->validated();

        $notification = DB::transaction(function () use ($data) {
            $filters = $data['filters'] ?? [];
            $userIds = $data['recipients'] ?? $this->segments->userIds($filters);

            if (($data['category'] ?? null) === 'marketing') {
                $filters['marketing_consent'] = true;
                $userIds = $data['recipients'] ?? $this->segments->userIds($filters);
            }

            $segment = null;
            if (!empty($data['save_segment']) || !empty($data['segment_name'])) {
                $segment = PushSegment::create([
                    'crm_id' => $data['external_id'] ?? null,
                    'name' => $data['segment_name'] ?? ('Segment ' . now()->format('Y-m-d H:i')),
                    'filters_json' => $filters,
                    'recipient_count' => count($userIds),
                ]);
            }

            $notification = PushNotification::create([
                'external_id' => $data['external_id'] ?? null,
                'title' => $data['title'],
                'body' => $data['body'],
                'category' => $data['category'],
                'type' => $data['type'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'image_url' => $data['image_url'] ?? null,
                'deep_link' => $data['deep_link'] ?? null,
                'payload_json' => $data['payload'] ?? [],
                'status' => !empty($data['scheduled_at']) ? PushNotification::STATUS_SCHEDULED : PushNotification::STATUS_DRAFT,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'created_by_crm_user_id' => $data['created_by_crm_user_id'] ?? null,
                'push_segment_id' => $segment ? $segment->id : null,
                'filters_json' => $filters,
                'recipient_count' => count($userIds),
            ]);

            $this->createRecipients($notification, $userIds);

            return $notification;
        });

        return response()->json([
            'success' => true,
            'id' => $notification->id,
            'status' => $notification->status,
            'recipient_count' => $notification->recipient_count,
        ], 201);
    }

    public function send(Request $request, int $id)
    {
        $this->authorizeCrm($request);
        $notification = PushNotification::findOrFail($id);
        SendPushNotificationJob::dispatch($notification->id);

        return response()->json(['success' => true, 'status' => 'queued']);
    }

    public function schedule(Request $request, int $id)
    {
        $this->authorizeCrm($request);
        $data = $request->validate(['scheduled_at' => ['required', 'date']]);

        $notification = PushNotification::findOrFail($id);
        $notification->update([
            'scheduled_at' => $data['scheduled_at'],
            'status' => PushNotification::STATUS_SCHEDULED,
        ]);

        return response()->json(['success' => true, 'status' => $notification->status]);
    }

    public function status(Request $request, int $id)
    {
        $this->authorizeCrm($request);
        $notification = PushNotification::withCount([
            'recipients',
            'recipients as pending_count' => fn ($q) => $q->where('status', 'pending'),
            'recipients as sent_recipients_count' => fn ($q) => $q->where('status', 'sent'),
            'recipients as failed_recipients_count' => fn ($q) => $q->where('status', 'failed'),
            'recipients as read_count' => fn ($q) => $q->whereNotNull('read_at'),
            'recipients as opened_count' => fn ($q) => $q->whereNotNull('opened_at'),
        ])->findOrFail($id);

        return response()->json(['success' => true, 'notification' => $notification]);
    }

    public function test(CrmPushTestRequest $request)
    {
        $this->authorizeCrm($request);
        $data = $request->validated();

        $notification = PushNotification::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'category' => $data['category'] ?? 'system',
            'type' => 'test',
            'priority' => 'high',
            'image_url' => $data['image_url'] ?? null,
            'deep_link' => $data['deep_link'] ?? null,
            'status' => PushNotification::STATUS_DRAFT,
            'recipient_count' => 1,
        ]);

        $tokenQuery = DeviceToken::where('is_active', true);
        if (!empty($data['device_token_id'])) {
            $tokenQuery->whereKey($data['device_token_id']);
        } else {
            $tokenQuery->where('user_id', $data['user_id']);
        }

        $deviceToken = $tokenQuery->latest('last_seen_at')->first();

        if (!$deviceToken) {
            $notification->delete();
            return response()->json([
                'success' => false,
                'message' => empty($data['device_token_id'])
                    ? "No active device token found for user_id={$data['user_id']}."
                    : "Device token #{$data['device_token_id']} not found or is inactive.",
            ], 404);
        }
        PushNotificationRecipient::create([
            'push_notification_id' => $notification->id,
            'user_id' => $deviceToken->user_id,
            'device_token_id' => $deviceToken->id,
            'status' => 'pending',
        ]);

        SendPushNotificationJob::dispatch($notification->id);

        return response()->json(['success' => true, 'id' => $notification->id, 'status' => 'queued']);
    }

    private function createRecipients(PushNotification $notification, array $userIds): void
    {
        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            $tokens = DeviceToken::where('user_id', $userId)->where('is_active', true)->get();

            if ($tokens->isEmpty()) {
                PushNotificationRecipient::firstOrCreate([
                    'push_notification_id' => $notification->id,
                    'user_id' => $userId,
                    'device_token_id' => null,
                ], ['status' => 'no_active_token']);
                continue;
            }

            foreach ($tokens as $token) {
                PushNotificationRecipient::firstOrCreate([
                    'push_notification_id' => $notification->id,
                    'user_id' => $userId,
                    'device_token_id' => $token->id,
                ], ['status' => 'pending']);
            }
        }
    }

    private function authorizeCrm(Request $request): void
    {
        $expected = config('services.crm.push_api_token');
        if (!$expected) {
            return;
        }

        abort_unless(hash_equals($expected, (string) $request->bearerToken()), 401, 'Invalid CRM push token');
    }
}