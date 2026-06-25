<?php

namespace App\Jobs;

use App\Exceptions\Order\CrmIntegrationException;
use App\Exceptions\Order\CrmOrderException;
use App\Exceptions\Order\LocalSyncValidationException;
use App\Models\OrderRequest;
use App\Services\Order\CrmOrderClient;
use App\Services\Order\OrderSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retry the local-sync step for an order that reached crm_success
 * but failed to write local projections.
 *
 * The job is idempotent: re-running it for an already local_synced order
 * is a no-op (early return).
 *
 * Retry strategy: up to 5 attempts with exponential back-off (30s → 16min).
 */
class SyncOrderJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $timeout = 60;
    /** @var int */
    private $orderRequestId;

    public function __construct(int $orderRequestId)
    {
        $this->orderRequestId = $orderRequestId;
    }

    public function handle(OrderSyncService $syncService, CrmOrderClient $crmClient): void
    {
        $orderRequest = OrderRequest::find($this->orderRequestId);

        if ($orderRequest === null) {
            Log::error('SyncOrderJob: OrderRequest not found', ['id' => $this->orderRequestId]);
            return;
        }

        // ── Idempotency: already done or not in a retriable state ─────────────
        if ($orderRequest->status === OrderRequest::STATUS_LOCAL_SYNCED) {
            Log::info('SyncOrderJob: already local_synced, skipping', ['guid' => $orderRequest->guid]);
            return;
        }

        if (!in_array($orderRequest->status, [
            OrderRequest::STATUS_LOCAL_SYNC_FAILED,
            OrderRequest::STATUS_CRM_SUCCESS,
        ], true)) {
            Log::warning('SyncOrderJob: unexpected status, skipping', [
                'guid'   => $orderRequest->guid,
                'status' => $orderRequest->status,
            ]);
            return;
        }

        // ── If CRM response data is missing, fetch a fresh snapshot ──────────
        if (empty($orderRequest->crm_response_json) && $orderRequest->crm_contracts_id) {
            try {
                $snapshot = $crmClient->fetchOrderByContractId(
                    $orderRequest->crm_contracts_id,
                    $orderRequest->guid,
                );
                $orderRequest->update(['crm_response_json' => $snapshot->raw]);
                $orderRequest->refresh();
            } catch (CrmOrderException | CrmIntegrationException $e) {
                Log::error('SyncOrderJob: failed to re-fetch CRM snapshot', [
                    'guid'  => $orderRequest->guid,
                    'error' => $e->getMessage(),
                ]);
                $this->fail($e);
                return;
            }
        }

        // ── Run sync ──────────────────────────────────────────────────────────
        try {
            $syncService->syncFromCrmResponse($orderRequest);

            $orderRequest->update([
                'status'        => OrderRequest::STATUS_LOCAL_SYNCED,
                'error_message' => null,
                'processed_at'  => now(),
            ]);

            Log::info('SyncOrderJob: local sync completed', ['guid' => $orderRequest->guid]);
        } catch (LocalSyncValidationException $e) {
            $orderRequest->increment('attempts');
            $orderRequest->update(['error_message' => $e->getMessage()]);

            Log::error('SyncOrderJob: local sync validation failed', [
                'guid'    => $orderRequest->guid,
                'attempt' => $this->attempts(),
                'error'   => $e->getMessage(),
                'details' => $e->details,
            ]);

            $this->release($this->backoffSeconds());
        } catch (\Throwable $e) {
            $orderRequest->increment('attempts');
            $orderRequest->update(['error_message' => $e->getMessage()]);

            Log::error('SyncOrderJob: unexpected error', [
                'guid'  => $orderRequest->guid,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Let Laravel handle retry scheduling via $tries
        }
    }

    /** Exponential back-off: 30s, 90s, 270s, 810s, 2430s */
    private function backoffSeconds(): int
    {
        return (int) min(30 * (3 ** ($this->attempts() - 1)), 3600);
    }

    public function failed(\Throwable $exception): void
    {
        DB::table('order_requests')
            ->where('id', $this->orderRequestId)
            ->update([
                'status'        => OrderRequest::STATUS_LOCAL_SYNC_FAILED,
                'error_message' => 'Retry exhausted: ' . $exception->getMessage(),
                'updated_at'    => now(),
            ]);

        Log::critical('SyncOrderJob: all retries exhausted', [
            'order_request_id' => $this->orderRequestId,
            'error'            => $exception->getMessage(),
        ]);
    }
}
