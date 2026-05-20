<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Ticket;
use App\Services\Order\CrmTicketOrderPayloadBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTicket(array $attrs = []): Ticket
    {
        static $counter = 1;
        return Ticket::create(array_merge([
            'crm_id'           => 9940000 + $counter++,
            'courses_headings_id' => 1,
            'products_id'      => 1,
            'title'            => 'Ticket ' . $counter,
            'offer_type'       => 'ticket',
            'website_status_id' => 1,
            'is_closed'        => 0,
            'available_places' => 100,
            'capacity'         => 500,
            'event_id'         => 1,
            'ticket_type'      => 'standard',
            'price_from'       => 49.99,
        ], $attrs));
    }

    // ─── Auth ────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_list_tickets(): void
    {
        $this->getJson('/api/offers/tickets')->assertStatus(401);
    }

    // ─── Listing ─────────────────────────────────────────────────────────────

    public function test_can_list_tickets(): void
    {
        $user = User::factory()->create();
        $this->makeTicket();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/tickets');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'body', 'recordCount']);
        $this->assertSame('200', $response->json('status'));
        $this->assertGreaterThanOrEqual(1, $response->json('recordCount'));
    }

    public function test_ticket_response_includes_ticket_fields(): void
    {
        $user = User::factory()->create();
        $this->makeTicket(['event_id' => 77, 'ticket_type' => 'vip', 'price_from' => 99.0]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/tickets');

        $response->assertStatus(200);
        $ticket = collect($response->json('body'))
            ->firstWhere('eventId', 77);

        $this->assertNotNull($ticket);
        $this->assertSame('vip',  $ticket['ticketType']);
        $this->assertSame(99.0,   $ticket['priceFrom']);
    }

    // ─── Filtering ───────────────────────────────────────────────────────────

    public function test_can_filter_by_event_id(): void
    {
        $user = User::factory()->create();
        $this->makeTicket(['event_id' => 10]);
        $this->makeTicket(['event_id' => 11]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/tickets?eventID=10');

        $response->assertStatus(200);
        foreach ($response->json('body') as $item) {
            $this->assertSame(10, $item['eventId']);
        }
    }

    public function test_can_filter_by_ticket_type(): void
    {
        $user = User::factory()->create();
        $this->makeTicket(['ticket_type' => 'vip']);
        $this->makeTicket(['ticket_type' => 'standard']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/tickets?ticketType=vip');

        $response->assertStatus(200);
        foreach ($response->json('body') as $item) {
            $this->assertSame('vip', $item['ticketType']);
        }
    }

    // ─── Show single ─────────────────────────────────────────────────────────

    public function test_can_show_ticket_by_crm_id(): void
    {
        $user   = User::factory()->create();
        $ticket = $this->makeTicket(['title' => 'Gala Event Ticket']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/offers/tickets/{$ticket->crm_id}")
            ->assertStatus(200)
            ->assertJsonPath('body.title', 'Gala Event Ticket');
    }

    public function test_show_ticket_returns_404_for_unknown_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/offers/tickets/999999999')
            ->assertStatus(404);
    }

    // ─── Order payload builder ───────────────────────────────────────────────

    public function test_ticket_order_payload_builder_includes_ticket_fields(): void
    {
        $payload = [
            'productsID'       => 88,
            'coursesHeadingsID' => 300,
            'allInstallments'  => [],
            'contractAmount'   => 99.0,
            'eventID'          => 42,
            'ticketType'       => 'vip',
        ];

        $result = CrmTicketOrderPayloadBuilder::build($payload, 'guid-ticket', 20, 0, 21);

        $this->assertSame(42,    $result['eventID']);
        $this->assertSame('vip', $result['ticketType']);
    }
}
