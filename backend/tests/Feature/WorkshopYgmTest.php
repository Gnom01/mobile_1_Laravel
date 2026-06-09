<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkshopYgm;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WorkshopYgmTest extends TestCase
{
    use DatabaseTransactions;

    private function makeWorkshop(array $attrs = []): WorkshopYgm
    {
        static $counter = 1;
        return WorkshopYgm::create(array_merge([
            'crm_id'           => 9900000 + $counter++,
            'courses_headings_id' => 1,
            'products_id'      => 1,
            'title'            => 'YGM Workshop ' . $counter,
            'offer_type'       => 'workshop_ygm',
            'website_status_id' => 1,
            'is_closed'        => 0,
            'starts_at'         => now()->toDateString(),
            'ends_at'           => now()->addDay()->toDateString(),
            'available_places' => 10,
            'capacity'         => 20,
        ], $attrs));
    }

    // ─── Auth ────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_list_workshops_ygm(): void
    {
        $this->getJson('/api/offers/workshops/ygm')
            ->assertStatus(401);
    }

    // ─── Listing ─────────────────────────────────────────────────────────────

    public function test_can_list_workshops_ygm(): void
    {
        $user = User::factory()->create();
        $this->makeWorkshop();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/workshops/ygm');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'body',
                'recordCount',
            ]);

        $this->assertSame('200', $response->json('status'));
        $this->assertGreaterThanOrEqual(1, $response->json('recordCount'));
    }

    // ─── Filtering ───────────────────────────────────────────────────────────

    public function test_can_filter_by_localization(): void
    {
        $user = User::factory()->create();
        $this->makeWorkshop(['localization_id' => 99]);
        $this->makeWorkshop(['localization_id' => 100]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/workshops/ygm?localizationID=99');

        $response->assertStatus(200);
        foreach ($response->json('body') as $item) {
            $this->assertSame(99, $item['localizationId']);
        }
    }

    public function test_available_only_filter_excludes_closed(): void
    {
        $user = User::factory()->create();
        $this->makeWorkshop(['is_closed' => 1, 'available_places' => 0]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/workshops/ygm?availableOnly=1');

        $response->assertStatus(200);
        foreach ($response->json('body') as $item) {
            $this->assertFalse($item['isClosed']);
        }
    }

    // ─── Show single ─────────────────────────────────────────────────────────

    public function test_can_show_workshop_ygm_by_crm_id(): void
    {
        $user     = User::factory()->create();
        $workshop = $this->makeWorkshop(['title' => 'Unique YGM']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/offers/workshops/ygm/{$workshop->crm_id}");

        $response->assertStatus(200)
            ->assertJsonPath('body.title', 'Unique YGM');
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/workshops/ygm/999999999')
            ->assertStatus(404);
    }

    public function test_calculate_pricing_rejects_invalid_category_for_ygm(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/offers/workshops/calculate-pricing', [
                'type' => 'ygm',
                'participantID' => 123,
                'categoryID' => 340, // 340 is Euro, invalid for ygm
            ])
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_checkout_rejects_invalid_products(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders/workshops/checkout', [
                'type' => 'ygm',
                'participantID' => 123,
                'categoryID' => 333,
                'selectedProductsIDs' => [999999], // non-existent
            ])
            ->assertStatus(400);
    }
}
