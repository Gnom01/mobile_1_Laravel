<?php

namespace Tests\Feature\Order;

use App\Exceptions\Order\LocalSyncValidationException;
use App\Jobs\SyncOrderJob;
use App\Models\Contract;
use App\Models\OrderRequest;
use App\Models\User;
use App\Services\Order\CrmOrderClient;
use App\Services\Order\OrderSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateOrderTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function validPayload(string $guid = null): array
    {
        return [
            'guid'              => $guid ?? (string) Str::uuid(),
            'contractSignature' => 'Nr/XYZ',
            'contractDate'      => '13-05-2026',
            'contractStartDate' => '2026-05-01',
            'contractEndDate'   => '2026-06-28',
            'allInstallmentsPrice' => 984.38,
            'entryFee'          => 0.0,
            'rawSelectedPricing' => [
                'productsID'                     => 24768,
                'priceListsTemplatesPositionsID' => 464,
                'amount'                         => 4500.00,
                'unitAmount'                     => 450.00,
                'paymentTypesDVID'               => 2,
                'periodsOfValidityDVID'          => 1,
                'numberOfUnitsAccount'           => 10,
                'paymentShedule'                 => [
                    [
                        'countNumber'                => 1,
                        'paymentDate'                => '2026-05-13',
                        'paymentPositionPrice'       => 492.19,
                        'isVoid'                     => 0,
                        'periodFromDate'             => '2026-05-01',
                        'paymentPositionPriceDiscount' => 450,
                        'discountCash'               => 42.19,
                        'discountProcent'            => 9.38,
                        'discountValue'              => ['7' => 42.19],
                    ],
                ],
            ],
            'rawCourseData' => [
                'coursesHeadingsID' => 9439,
                'courseHeadingName' => 'B-Kuba',
            ],
            'payerUser' => [
                'firstName' => 'Jan',
                'lastName'  => 'Kowalski',
                'phone'     => '500000000',
                'email'     => 'jan@example.com',
            ],
            'installments' => [
                [
                    'amount'      => 1,
                    'paymentDate' => '2026-05-13',
                    'paymentPositionPrice' => 492.19,
                    'paymentPositionPriceDiscount' => 450,
                    'discountCash'   => 42.19,
                    'discountProcent'=> 9.38,
                    'periodFromDate' => '2026-05-01',
                    'periodToDate'   => '2026-05-31',
                    'paymentMonth'   => '2026-05-01',
                    'discountValue'  => ['7' => 42.19],
                    'discountFromDate' => ['7' => '2026-05-01'],
                ],
            ],
            'groupData' => [
                'periodsOfValidityDVID' => 1,
                'paymentTypesDVID'      => 2,
                'paymentDVIDName'       => 'ratalna',
            ],
            'payZero' => [
                'installmentZero'              => 492.19,
                'amountZero'                   => 450.0,
                'discountCashZero'             => 42.19,
                'installmentZeroAfterDiscount' => 450.0,
            ],
            'courseData' => [
                'clientsCyti'      => 'Warszawa',
                'contractHeader'   => 'EGURROLA DANCE STUDIO',
                'banckAccountNumber' => '00 1234 5678',
                'courseHeadingName'=> 'B-Kuba',
                'Frequency'        => '2',
                'DurationMin'      => '120',
            ],
        ];
    }

    private function crmSuccessResponse(int $contractsId = 12345): array
    {
        return [
            'contractsID'          => $contractsId,
            'usersProductsID'      => 67890,
            'paymentsID'           => 11111,
            'paymentToken'         => 'tok_abc123',
            'paymentUrl'           => 'https://pay.example.com/token/tok_abc123',
            'contract'             => ['contractsID' => $contractsId],
            'usersPaymentsSchedules' => [
                ['usersPaymentsSchedulesID' => 1, 'contractsID' => $contractsId, 'paymentAmount' => 450.00],
            ],
            'payments'   => [['paymentsID' => 11111, 'paymentAmount' => 450.00]],
            'paymentsItems' => [],
            'usersProducts'  => [['usersProductsID' => 67890]],
            'usersBaskets'   => [],
        ];
    }

    // ─── Scenario 1: New order — full happy path ───────────────────────────────

    public function test_new_order_creates_request_calls_crm_syncs_locally(): void
    {
        Queue::fake();

        $this->instance(CrmOrderClient::class, Mockery::mock(CrmOrderClient::class, function (MockInterface $m): void {
            $m->shouldReceive('createOrder')
                ->once()
                ->andReturn(\App\Data\Order\CrmOrderResponse::fromArray($this->crmSuccessResponse()));
        }));

        $this->instance(OrderSyncService::class, Mockery::mock(OrderSyncService::class, function (MockInterface $m): void {
            $m->shouldReceive('syncFromCrmResponse')
                ->once()
                ->andReturn(new \App\Data\Order\SyncResult(true, 12345, 1, 1, 'test-guid'));
        }));

        $payload = $this->validPayload();

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', OrderRequest::STATUS_LOCAL_SYNCED)
            ->assertJsonPath('data.crm_contracts_id', 12345)
            ->assertJsonPath('data.was_already_processed', false);

        $this->assertDatabaseHas('order_requests', [
            'guid'   => $payload['guid'],
            'status' => OrderRequest::STATUS_LOCAL_SYNCED,
        ]);

        Queue::assertNothingPushed();
    }

    // ─── Scenario 2: Same guid second time → cached result, CRM not called ────

    public function test_duplicate_guid_returns_previous_result_without_calling_crm(): void
    {
        Queue::fake();
        $guid = (string) Str::uuid();

        // Seed an existing successful order_request
        $existing = OrderRequest::create([
            'guid'             => $guid,
            'user_id'          => $this->user->id,
            'payer_user_id'    => $this->user->id,
            'status'           => OrderRequest::STATUS_LOCAL_SYNCED,
            'payload_hash'     => hash('sha256', json_encode($this->validPayload($guid))),
            'payload_json'     => $this->validPayload($guid),
            'crm_response_json'=> $this->crmSuccessResponse(),
            'crm_contracts_id' => 12345,
            'crm_payments_id'  => 11111,
            'payment_token'    => 'tok_abc123',
            'payment_url'      => 'https://pay.example.com/token/tok_abc123',
        ]);

        // CRM must NOT be called
        $this->instance(CrmOrderClient::class, Mockery::mock(CrmOrderClient::class, function (MockInterface $m): void {
            $m->shouldNotReceive('createOrder');
        }));

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', $this->validPayload($guid));

        $response->assertStatus(200)
            ->assertJsonPath('data.guid', $guid)
            ->assertJsonPath('data.was_already_processed', true);
    }

    // ─── Scenario 3: CRM returns 400 → crm_failed, no local records ──────────

    public function test_crm_400_sets_crm_failed_no_local_projections(): void
    {
        Queue::fake();

        $this->instance(CrmOrderClient::class, Mockery::mock(CrmOrderClient::class, function (MockInterface $m): void {
            $m->shouldReceive('createOrder')
                ->once()
                ->andThrow(new \App\Exceptions\Order\CrmOrderException('Invalid product', 400));
        }));

        $payload  = $this->validPayload();
        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'crm_order_failed');

        $this->assertDatabaseHas('order_requests', [
            'guid'   => $payload['guid'],
            'status' => OrderRequest::STATUS_CRM_FAILED,
        ]);

        $this->assertDatabaseMissing('contracts', ['crm_order_guid' => $payload['guid']]);
    }

    // ─── Scenario 4: CRM returns 500 → crm_failed / integration error ─────────

    public function test_crm_500_sets_crm_failed_returns_503(): void
    {
        Queue::fake();

        $this->instance(CrmOrderClient::class, Mockery::mock(CrmOrderClient::class, function (MockInterface $m): void {
            $m->shouldReceive('createOrder')
                ->once()
                ->andThrow(new \App\Exceptions\Order\CrmIntegrationException('CRM server error 500', 500));
        }));

        $payload  = $this->validPayload();
        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', $payload);

        $response->assertStatus(503);

        $this->assertDatabaseHas('order_requests', [
            'guid'   => $payload['guid'],
            'status' => OrderRequest::STATUS_CRM_FAILED,
        ]);
    }

    // ─── Scenario 5: CRM success, local sync fails → local_sync_failed + job ─

    public function test_crm_success_local_sync_failure_dispatches_retry_job(): void
    {
        Queue::fake();

        $this->instance(CrmOrderClient::class, Mockery::mock(CrmOrderClient::class, function (MockInterface $m): void {
            $m->shouldReceive('createOrder')
                ->once()
                ->andReturn(\App\Data\Order\CrmOrderResponse::fromArray($this->crmSuccessResponse()));
        }));

        $this->instance(OrderSyncService::class, Mockery::mock(OrderSyncService::class, function (MockInterface $m): void {
            $m->shouldReceive('syncFromCrmResponse')
                ->once()
                ->andThrow(new LocalSyncValidationException('Mismatch'));
        }));

        $payload  = $this->validPayload();
        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', $payload);

        // API still returns 201 with partial result; retry happens in background
        $response->assertStatus(201);

        $this->assertDatabaseHas('order_requests', [
            'guid'   => $payload['guid'],
            'status' => OrderRequest::STATUS_LOCAL_SYNC_FAILED,
        ]);

        Queue::assertPushed(SyncOrderJob::class);
    }

    // ─── Scenario 6: Retry job is idempotent ──────────────────────────────────

    public function test_retry_job_is_idempotent_no_duplicates(): void
    {
        $req = OrderRequest::create([
            'guid'              => (string) Str::uuid(),
            'user_id'           => $this->user->id,
            'payer_user_id'     => $this->user->id,
            'status'            => OrderRequest::STATUS_LOCAL_SYNC_FAILED,
            'payload_hash'      => 'abc',
            'payload_json'      => [],
            'crm_response_json' => $this->crmSuccessResponse(),
            'crm_contracts_id'  => 12345,
        ]);

        $syncMock = Mockery::mock(OrderSyncService::class);
        $syncMock->shouldReceive('syncFromCrmResponse')
            ->once()
            ->andReturn(new \App\Data\Order\SyncResult(true, 12345, 1, 1, $req->guid));

        $crmMock = Mockery::mock(CrmOrderClient::class);

        $job = new SyncOrderJob($req->id);
        $job->handle($syncMock, $crmMock);
        $job->handle($syncMock, $crmMock); // second run — already local_synced

        $this->assertDatabaseHas('order_requests', [
            'id'     => $req->id,
            'status' => OrderRequest::STATUS_LOCAL_SYNCED,
        ]);

        // Only one contract row regardless of how many times sync ran
        $this->assertDatabaseCount('order_requests', 1);
    }

    // ─── Scenario 7: Race condition — two requests, only one order_request ────

    public function test_race_condition_only_one_order_request_created(): void
    {
        Queue::fake();
        $guid = (string) Str::uuid();

        $this->instance(CrmOrderClient::class, Mockery::mock(CrmOrderClient::class, function (MockInterface $m): void {
            $m->shouldReceive('createOrder')
                ->andReturn(\App\Data\Order\CrmOrderResponse::fromArray($this->crmSuccessResponse()));
        }));

        $this->instance(OrderSyncService::class, Mockery::mock(OrderSyncService::class, function (MockInterface $m): void {
            $m->shouldReceive('syncFromCrmResponse')
                ->andReturn(new \App\Data\Order\SyncResult(true, 12345, 1, 1, 'guid'));
        }));

        // Simulate two near-simultaneous requests sequentially
        $this->actingAs($this->user)->postJson('/api/orders', $this->validPayload($guid));
        $this->actingAs($this->user)->postJson('/api/orders', $this->validPayload($guid));

        $this->assertDatabaseCount('order_requests', 1);
    }

    // ─── Scenario 8: Same guid, different payload → 409 conflict ─────────────

    public function test_same_guid_different_payload_throws_idempotency_conflict(): void
    {
        Queue::fake();
        $guid = (string) Str::uuid();

        $originalPayload = $this->validPayload($guid);
        $differentPayload = $this->validPayload($guid);
        $differentPayload['allInstallmentsPrice'] = 9999.99; // changed

        // Create the first order_request
        OrderRequest::create([
            'guid'          => $guid,
            'user_id'       => $this->user->id,
            'payer_user_id' => $this->user->id,
            'status'        => OrderRequest::STATUS_LOCAL_SYNCED,
            'payload_hash'  => hash('sha256', json_encode($originalPayload)),
            'payload_json'  => $originalPayload,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', $differentPayload);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'idempotency_conflict');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}