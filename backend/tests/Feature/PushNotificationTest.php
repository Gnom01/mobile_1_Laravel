<?php

namespace Tests\Feature;

use App\Models\CrmUser;
use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Models\PushNotificationRecipient;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    public function test_device_token_can_be_registered(): void
    {
        $user = CrmUser::query()->first();
        if (!$user) {
            $this->markTestSkipped('No CRM user available in the local test database.');
        }

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/mobile/device-tokens', [
            'platform' => 'android',
            'token' => 'test-token-' . uniqid(),
            'device_id' => 'feature-test-device',
            'app_version' => '1.0.0+1',
            'locale' => 'pl_PL',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_crm_can_create_notification(): void
    {
        $user = CrmUser::query()->first();
        if (!$user) {
            $this->markTestSkipped('No CRM user available in the local test database.');
        }

        DeviceToken::updateOrCreate(
            ['token_hash' => DeviceToken::hashToken('crm-create-test-token')],
            [
                'user_id' => $user->UsersID,
                'platform' => 'android',
                'token' => 'crm-create-test-token',
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );

        $response = $this->postJson('/api/crm/push/notifications', [
            'title' => 'Test push',
            'body' => 'Test body',
            'category' => 'system',
            'recipients' => [$user->UsersID],
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('recipient_count', 1);
    }

    public function test_notification_list_and_read_flow(): void
    {
        $user = CrmUser::query()->first();
        if (!$user) {
            $this->markTestSkipped('No CRM user available in the local test database.');
        }

        $notification = PushNotification::create([
            'title' => 'List test',
            'body' => 'Visible in bell',
            'category' => 'system',
            'status' => PushNotification::STATUS_SENT,
            'recipient_count' => 1,
        ]);

        PushNotificationRecipient::create([
            'push_notification_id' => $notification->id,
            'user_id' => $user->UsersID,
            'status' => 'sent',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/mobile/notifications')
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'List test']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/mobile/notifications/' . $notification->id . '/read')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
