<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DayCamp;
use App\Services\Order\CrmDayCampOrderPayloadBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DayCampTest extends TestCase
{
    use DatabaseTransactions;

    private function makeDayCamp(array $attrs = []): DayCamp
    {
        static $counter = 1;
        return DayCamp::create(array_merge([
            'crm_id'           => 9930000 + $counter++,
            'courses_headings_id' => 1,
            'products_id'      => 1,
            'title'            => 'Day Camp ' . $counter,
            'offer_type'       => 'day_camp',
            'website_status_id' => 1,
            'is_closed'        => 0,
            'available_places' => 8,
            'capacity'         => 20,
        ], $attrs));
    }

    // ─── Auth ────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_list_day_camps(): void
    {
        $this->getJson('/api/offers/day-camps')->assertStatus(401);
    }

    // ─── Listing ─────────────────────────────────────────────────────────────

    public function test_can_list_day_camps(): void
    {
        $user = User::factory()->create();
        $this->makeDayCamp();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/day-camps');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'body', 'recordCount']);
        $this->assertSame('200', $response->json('status'));
    }

    public function test_day_camp_response_includes_camp_fields(): void
    {
        $user = User::factory()->create();
        $this->makeDayCamp(['turnus_name' => 'Turnus DC1']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/day-camps');

        $response->assertStatus(200);
        $first = collect($response->json('body'))->first();
        $this->assertArrayHasKey('turnusName',       $first);
        $this->assertArrayHasKey('departurePlace',   $first);
        $this->assertArrayHasKey('transportOptions', $first);
        $this->assertArrayHasKey('dietOptions',      $first);
    }

    // ─── Show single ─────────────────────────────────────────────────────────

    public function test_can_show_day_camp_by_crm_id(): void
    {
        $user    = User::factory()->create();
        $dayCamp = $this->makeDayCamp(['title' => 'City Day Camp']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/offers/day-camps/{$dayCamp->crm_id}")
            ->assertStatus(200)
            ->assertJsonPath('body.title', 'City Day Camp');
    }

    public function test_show_day_camp_returns_404_for_unknown_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/day-camps/999999999')
            ->assertStatus(404);
    }

    // ─── Order payload builder ───────────────────────────────────────────────

    public function test_day_camp_order_payload_builder_includes_camp_fields(): void
    {
        $payload = [
            'productsID'       => 55,
            'coursesHeadingsID' => 200,
            'allInstallments'  => [],
            'contractAmount'   => 300.0,
            'turnusName'       => 'Turnus DC',
            'departurePlace'   => 'Gdańsk',
            'transportOptions' => [],
            'dietOptions'      => ['standard'],
            'medicalRequired'  => 0,
            'guardianRequired' => 1,
        ];

        $result = CrmDayCampOrderPayloadBuilder::build($payload, 'guid-dc', 10, 0, 11);

        $this->assertSame('Turnus DC', $result['turnusName']);
        $this->assertSame('Gdańsk',    $result['departurePlace']);
        $this->assertSame(1,           $result['guardianRequired']);
    }
}
