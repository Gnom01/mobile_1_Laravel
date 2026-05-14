<?php

namespace Tests\Unit\Order;

use App\Data\Order\CreateOrderData;
use App\Data\Order\CrmOrderResponse;
use App\Data\Order\OrderResult;
use App\Data\Order\SyncResult;
use App\Exceptions\Order\CrmOrderException;
use App\Exceptions\Order\LocalSyncValidationException;
use App\Exceptions\Order\OrderAlreadyProcessingException;
use App\Exceptions\Order\OrderIdempotencyConflictException;
use App\Jobs\SyncOrderJob;
use App\Models\OrderRequest;
use App\Services\Order\CrmOrderClient;
use App\Services\Order\OrderApplicationService;
use App\Services\Order\OrderSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OrderApplicationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CrmOrderClient&MockInterface  $crmClient;
    private OrderSyncService&MockInterface $syncService;
    private OrderApplicationService        $service;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->crmClient   = Mockery::mock(CrmOrderClient::class);
        $this->syncService = Mockery::mock(OrderSyncService::class);
        $this->service     = new OrderApplicationService($this->crmClient, $this->syncService);
    }

    private function makeData(string $guid = null, array $payload = []): CreateOrderData
    {
        $payload = array_merge(['foo' => 'bar'], $payload);
        return new CreateOrderData(
            guid:        $guid ?? (string) Str::uuid(),
            userId:      1,
            payerUserId: 1,
            payload:     $payload,
        );
    }

    private function makeCrmResponse(int $contractsId = 100): CrmOrderResponse
    {
        return CrmOrderResponse::fromArray([
            'contractsID'     => $contractsId,
            'usersProductsID' => 200,
            'paymentsID'      => 300,
            'paymentToken'    => 'tok_x',
            'paymentUrl'      => 'https://pay.example.com',
        ]);
    }

    private function makeSyncResult(int $contractsId = 100, string $guid = 'g'): SyncResult
    {
        return new SyncResult(true, $contractsId, 1, 1, $guid);
    }

    // ─── Happy path ────────────────────────────────────────────────────────────

    public function test_creates_order_request_and_returns_local_synced(): void
    {
        $data = $this->makeData();

        $this->crmClient->shouldReceive('createOrder')->once()->andReturn($this->makeCrmResponse());
        $this->syncService->shouldReceive('syncFromCrmResponse')->once()->andReturn($this->makeSyncResult(100, $data->guid));

        $result = $this->service->createOrder($data);

        $this->assertInstanceOf(OrderResult::class, $result);
        $this->assertSame(OrderRequest::STATUS_LOCAL_SYNCED, $result->status);
        $this->assertSame(100, $result->crmContractsId);
        $this->assertFalse($result->wasAlreadyProcessed);

        $this->assertDatabaseHas('order_requests', [
            'guid'   => $data->guid,
            'status' => OrderRequest::STATUS_LOCAL_SYNCED,
        ]);
    }

    // ─── Idempotency: same guid, already synced ────────────────────────────────

    public function test_returns_cached_result_for_already_synced_guid(): void
    {
        $data = $this->makeData();
        $hash = hash('sha256', json_encode($data->payload));

        OrderRequest::create([
            'guid'              => $data->guid,
            'user_id'           => 1,
            'payer_user_id'     => 1,
            'status'            => OrderRequest::STATUS_LOCAL_SYNCED,
            'payload_hash'      => $hash,
            'payload_json'      => $data->payload,
            'crm_contracts_id'  => 100,
            'payment_token'     => 'tok_x',
        ]);

        $this->crmClient->shouldNotReceive('createOrder');
        $this->syncService->shouldNotReceive('syncFromCrmResponse');

        $result = $this->service->createOrder($data);

        $this->assertTrue($result->wasAlreadyProcessed);
        $this->assertSame(OrderRequest::STATUS_LOCAL_SYNCED, $result->status);
    }

    // ─── Idempotency conflict ──────────────────────────────────────────────────

    public function test_throws_idempotency_conflict_when_payload_changes(): void
    {
        $guid = (string) Str::uuid();
        $data = $this->makeData($guid, ['foo' => 'bar']);

        OrderRequest::create([
            'guid'         => $guid,
            'user_id'      => 1,
            'payer_user_id'=> 1,
            'status'       => OrderRequest::STATUS_PENDING,
            'payload_hash' => hash('sha256', json_encode(['foo' => 'DIFFERENT'])),
            'payload_json' => ['foo' => 'DIFFERENT'],
        ]);

        $this->expectException(OrderIdempotencyConflictException::class);
        $this->service->createOrder($data);
    }

    // ─── Processing lock ───────────────────────────────────────────────────────

    public function test_throws_already_processing_when_lock_is_fresh(): void
    {
        $data = $this->makeData();
        $hash = hash('sha256', json_encode($data->payload));

        OrderRequest::create([
            'guid'         => $data->guid,
            'user_id'      => 1,
            'payer_user_id'=> 1,
            'status'       => OrderRequest::STATUS_PROCESSING,
            'payload_hash' => $hash,
            'payload_json' => $data->payload,
            'locked_at'    => now()->subSeconds(10), // fresh
        ]);

        $this->expectException(OrderAlreadyProcessingException::class);
        $this->service->createOrder($data);
    }

    // ─── CRM failure ──────────────────────────────────────────────────────────

    public function test_sets_crm_failed_when_crm_returns_business_error(): void
    {
        $data = $this->makeData();

        $this->crmClient->shouldReceive('createOrder')
            ->once()
            ->andThrow(new CrmOrderException('Bad request', 400));
        $this->syncService->shouldNotReceive('syncFromCrmResponse');

        $this->expectException(CrmOrderException::class);

        try {
            $this->service->createOrder($data);
        } finally {
            $this->assertDatabaseHas('order_requests', [
                'guid'   => $data->guid,
                'status' => OrderRequest::STATUS_CRM_FAILED,
            ]);
        }
    }

    // ─── Local sync failure → retry job ───────────────────────────────────────

    public function test_dispatches_retry_job_when_local_sync_fails(): void
    {
        $data = $this->makeData();

        $this->crmClient->shouldReceive('createOrder')->once()->andReturn($this->makeCrmResponse());
        $this->syncService->shouldReceive('syncFromCrmResponse')
            ->once()
            ->andThrow(new LocalSyncValidationException('Mismatch'));

        $result = $this->service->createOrder($data);

        $this->assertSame(OrderRequest::STATUS_LOCAL_SYNC_FAILED, $result->status);

        Queue::assertPushed(SyncOrderJob::class, function (SyncOrderJob $job) use ($data): bool {
            // We can only check it was dispatched; the job carries a private id.
            return true;
        });
    }

    // ─── Attempts counter ─────────────────────────────────────────────────────

    public function test_attempts_counter_increments_on_each_processing(): void
    {
        $data = $this->makeData();
        $hash = hash('sha256', json_encode($data->payload));

        // Seed a stale processing record (lock expired)
        OrderRequest::create([
            'guid'         => $data->guid,
            'user_id'      => 1,
            'payer_user_id'=> 1,
            'status'       => OrderRequest::STATUS_PROCESSING,
            'payload_hash' => $hash,
            'payload_json' => $data->payload,
            'locked_at'    => now()->subSeconds(OrderRequest::PROCESSING_LOCK_TTL + 10),
            'attempts'     => 1,
        ]);

        $this->crmClient->shouldReceive('createOrder')->once()->andReturn($this->makeCrmResponse());
        $this->syncService->shouldReceive('syncFromCrmResponse')->once()->andReturn($this->makeSyncResult(100, $data->guid));

        $this->service->createOrder($data);

        $req = OrderRequest::where('guid', $data->guid)->first();
        $this->assertSame(2, $req->attempts);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
