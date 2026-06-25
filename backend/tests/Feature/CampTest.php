<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Camp;
use App\Services\Order\CrmCampOrderPayloadBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CampTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCamp(array $attrs = []): Camp
    {
        static $counter = 1;
        return Camp::create(array_merge([
            'crm_id'           => 9920000 + $counter++,
            'courses_headings_id' => 1,
            'products_id'      => 1,
            'title'            => 'Camp ' . $counter,
            'offer_type'       => 'camp',
            'website_status_id' => 1,
            'is_closed'        => 0,
            'available_places' => 5,
            'capacity'         => 20,
        ], $attrs));
    }

    // ─── Auth ────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_list_camps(): void
    {
        $this->getJson('/api/offers/camps')->assertStatus(401);
    }

    // ─── Listing ─────────────────────────────────────────────────────────────

    public function test_can_list_camps(): void
    {
        $user = User::factory()->create();
        $this->makeCamp();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/camps');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'body', 'recordCount']);
        $this->assertSame('200', $response->json('status'));
        $this->assertGreaterThanOrEqual(1, $response->json('recordCount'));
    }

    public function test_camp_response_includes_camp_fields(): void
    {
        $user = User::factory()->create();
        $this->makeCamp([
            'turnus_name'     => 'Turnus A',
            'departure_place' => 'Warszawa',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/camps');

        $response->assertStatus(200);
        $first = collect($response->json('body'))->first();
        $this->assertArrayHasKey('turnusName',     $first);
        $this->assertArrayHasKey('departurePlace', $first);
        $this->assertArrayHasKey('transportOptions', $first);
        $this->assertArrayHasKey('dietOptions',    $first);
    }

    // ─── Filtering ───────────────────────────────────────────────────────────

    public function test_available_only_filter_excludes_full_camps(): void
    {
        $user = User::factory()->create();
        $this->makeCamp(['is_closed' => 1, 'available_places' => 0]);
        $this->makeCamp(['is_closed' => 0, 'available_places' => 3]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/camps?availableOnly=1');

        $response->assertStatus(200);
        foreach ($response->json('body') as $item) {
            $this->assertFalse($item['isClosed']);
            $this->assertGreaterThan(0, $item['availablePlaces']);
        }
    }

    // ─── Show single ─────────────────────────────────────────────────────────

    public function test_can_show_camp_by_crm_id(): void
    {
        $user = User::factory()->create();
        $camp = $this->makeCamp(['title' => 'Summer Camp']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/offers/camps/{$camp->crm_id}")
            ->assertStatus(200)
            ->assertJsonPath('body.title', 'Summer Camp');
    }

    public function test_show_camp_returns_404_for_unknown_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/camps/999999999')
            ->assertStatus(404);
    }

    // ─── Order payload builder ───────────────────────────────────────────────

    public function test_camp_order_payload_builder_includes_camp_fields(): void
    {
        $payload = [
            'productsID'           => 42,
            'coursesHeadingsID'    => 100,
            'allInstallments'      => [],
            'contractAmount'       => 500.0,
            'turnusName'           => 'Turnus B',
            'departurePlace'       => 'Kraków',
            'transportOptions'     => ['bus'],
            'dietOptions'          => ['vege'],
            'medicalRequired'      => 1,
            'guardianRequired'     => 0,
        ];

        $result = CrmCampOrderPayloadBuilder::build($payload, 'test-guid', 1, 0, 2);

        $this->assertSame('Turnus B', $result['turnusName']);
        $this->assertSame('Kraków',   $result['departurePlace']);
        $this->assertSame(['bus'],    $result['transportOptions']);
        $this->assertSame(['vege'],   $result['dietOptions']);
        $this->assertSame(1,          $result['medicalRequired']);
        $this->assertSame(0,          $result['guardianRequired']);
    }
}
