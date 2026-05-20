<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkshopEuropean;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WorkshopEuropeanTest extends TestCase
{
    use DatabaseTransactions;

    private function makeWorkshop(array $attrs = []): WorkshopEuropean
    {
        static $counter = 1;
        return WorkshopEuropean::create(array_merge([
            'crm_id'           => 9910000 + $counter++,
            'courses_headings_id' => 1,
            'products_id'      => 1,
            'title'            => 'European Workshop ' . $counter,
            'offer_type'       => 'workshop_european',
            'website_status_id' => 1,
            'is_closed'        => 0,
            'available_places' => 10,
            'capacity'         => 20,
        ], $attrs));
    }

    public function test_unauthenticated_user_cannot_list_workshops_european(): void
    {
        $this->getJson('/api/offers/workshops/european')
            ->assertStatus(401);
    }

    public function test_can_list_workshops_european(): void
    {
        $user = User::factory()->create();
        $this->makeWorkshop();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/workshops/european');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'body', 'recordCount']);
        $this->assertSame('200', $response->json('status'));
    }

    public function test_can_filter_european_by_category(): void
    {
        $user = User::factory()->create();
        $this->makeWorkshop(['category_id' => 5]);
        $this->makeWorkshop(['category_id' => 6]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/workshops/european?categoryID=5');

        $response->assertStatus(200);
        foreach ($response->json('body') as $item) {
            $this->assertSame(5, $item['categoryId']);
        }
    }

    public function test_can_show_workshop_european_by_crm_id(): void
    {
        $user     = User::factory()->create();
        $workshop = $this->makeWorkshop(['title' => 'Euro Workshop']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/offers/workshops/european/{$workshop->crm_id}");

        $response->assertStatus(200)
            ->assertJsonPath('body.title', 'Euro Workshop');
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/workshops/european/999999999')
            ->assertStatus(404);
    }
}
