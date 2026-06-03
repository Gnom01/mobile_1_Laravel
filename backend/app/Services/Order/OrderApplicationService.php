<?php

namespace App\Services\Order;

use App\Data\Order\CreateOrderData;
use App\Data\Order\OrderResult;
use App\Exceptions\Order\CrmIntegrationException;
use App\Exceptions\Order\CrmOrderException;
use App\Exceptions\Order\LocalSyncValidationException;
use App\Exceptions\Order\OrderAlreadyProcessingException;
use App\Exceptions\Order\OrderIdempotencyConflictException;
use App\Jobs\SyncOrderJob;
use App\Models\OrderRequest;
use App\Services\Order\CrmOrderPayloadBuilder;
use App\Services\Order\CrmCampOrderPayloadBuilder;
use App\Services\Order\CrmDayCampOrderPayloadBuilder;
use App\Services\Order\CrmTicketOrderPayloadBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderApplicationService
{
    /** @var CrmOrderClient */
    private $crmClient;

    /** @var OrderSyncService */
    private $syncService;

    public function __construct(CrmOrderClient $crmClient, OrderSyncService $syncService)
    {
        $this->crmClient = $crmClient;
        $this->syncService = $syncService;
    }

    /**
     * Create or idempotently return an order.
     *
     * Flow:
     *   1. Hash payload.
     *   2. Find-or-create OrderRequest by guid (inside short transaction).
     *   3. If already successful → return cached result.
     *   4. If processing and lock is fresh → throw.
     *   5. Mark processing, bump attempts, set locked_at.
     *   6. Call CRM (outside DB transaction).
     *   7. On CRM error → mark crm_failed, throw.
     *   8. On CRM success → persist response, mark crm_success.
     *   9. Run local sync.
     *  10. On sync ok  → mark local_synced.
     *  11. On sync fail → mark local_sync_failed, dispatch retry job.
     *  12. Return OrderResult.
     */
    public function createOrder(CreateOrderData $data): OrderResult
    {
        $payloadHash = hash('sha256', json_encode($data->payload));

        // ── Step 2: find-or-create inside short, locked transaction ───────────
        $orderRequest = DB::transaction(function () use ($data, $payloadHash): OrderRequest {
            /** @var OrderRequest $req */
            $req = OrderRequest::lockForUpdate()
                ->firstOrCreate(
                    ['guid' => $data->guid],
                    [
                        'user_id'       => $data->userId,
                        'payer_user_id' => $data->payerUserId,
                        'status'        => OrderRequest::STATUS_PENDING,
                        'payload_hash'  => $payloadHash,
                        'payload_json'  => $data->payload,
                    ],
                );

            // ── Step 8: idempotency conflict check ────────────────────────────
            if ($req->payload_hash !== $payloadHash) {
                throw new OrderIdempotencyConflictException($data->guid);
            }

            // ── Step 3: already done → return early flag ──────────────────────
            if ($req->isAlreadySuccessful()) {
                return $req;
            }

            // ── Step 4: processing lock ───────────────────────────────────────
            if ($req->isProcessingFresh()) {
                throw new OrderAlreadyProcessingException($data->guid);
            }

            // ── Step 5: claim processing slot ─────────────────────────────────
            $req->status    = OrderRequest::STATUS_PROCESSING;
            $req->attempts  = $req->attempts + 1;
            $req->locked_at = now();
            $req->save();

            return $req;
        });

        // ── Step 3 early-return path ──────────────────────────────────────────
        if ($orderRequest->isAlreadySuccessful()) {
            return OrderResult::fromOrderRequest($orderRequest, true);
        }

        // ── Step 6: call CRM — outside DB transaction ─────────────────────────
        try {
            $crmUser     = \App\Models\CrmUser::find($data->userId);
            $defaultLocId = (int) ($crmUser->Default_LocalizationsID ?? 0);
            $offerType = $orderRequest->payload_json['offerType'] ?? $orderRequest->payload_json['offer_type'] ?? null;
            if ($offerType === 'camp') {
                $crmBody = CrmCampOrderPayloadBuilder::build($orderRequest->payload_json, $data->guid, $data->userId, $defaultLocId, $data->participantUsersId);
            } elseif ($offerType === 'dayCamp') {
                $crmBody = CrmDayCampOrderPayloadBuilder::build($orderRequest->payload_json, $data->guid, $data->userId, $defaultLocId, $data->participantUsersId);
            } elseif ($offerType === 'ticket') {
                $crmBody = CrmTicketOrderPayloadBuilder::build($orderRequest->payload_json, $data->guid, $data->userId, $defaultLocId, $data->participantUsersId);
            } else {
                $crmBody = CrmOrderPayloadBuilder::build($orderRequest->payload_json, $data->guid, $data->userId, $defaultLocId, $data->participantUsersId);
            }
            $crmResponse = $this->crmClient->createOrder($crmBody, $data->guid);
        } catch (CrmOrderException | CrmIntegrationException $e) {
            // ── Step 7: CRM failure ───────────────────────────────────────────
            $orderRequest->update([
                'status'        => OrderRequest::STATUS_CRM_FAILED,
                'error_message' => $e->getMessage(),
                'locked_at'     => null,
            ]);

            Log::warning('CRM order creation failed', [
                'guid'  => $data->guid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // ── Step 7.5: initiate online payment (non-fatal) ────────────────────────
        // CRM createOrder only creates documents; payment URL must be obtained by a
        // separate call to OnLineSchedulePayment with the newly created schedule IDs.
        $paymentToken = null;
        $paymentUrl   = null;

        if ((int) ($crmBody['paymentMethodsDVID'] ?? 0) === 5 && $crmResponse->contractsId > 0) {
            try {
                $localizationsId = (int) ($crmBody['current_LocalizationsID'] ?? 0);
                $usersId         = (int) ($crmBody['usersID'] ?? $data->userId);

                $schedules = $this->crmClient->fetchPaymentSchedules(
                    $crmResponse->contractsId,
                    $usersId,
                    $localizationsId,
                    $data->guid
                );

                // CRM uses typo key 'instalmets' (not 'installments')
                $scheduleIds = array_values(array_filter(
                    array_map(
                        fn ($s) => (int) ($s['usersPaymentsSchedulesID'] ?? 0),
                        $schedules['instalmets'] ?? []
                    )
                ));

                if (!empty($scheduleIds)) {
                    $returnUrl   = (string) ($crmBody['returnUrl'] ?? config('services.crm.mobile_checkout_return_url', ''));
                    $paymentData = $this->crmClient->initiateOnlinePayment(
                        $scheduleIds,
                        $usersId,
                        $localizationsId,
                        5,
                        $returnUrl,
                        $data->guid
                    );

                    $paymentToken = $paymentData['token'] ?? $paymentData['html'] ?? null;
                    if ($paymentToken) {
                        // CRM zwraca gotowy URL płatności w polu 'token' — używamy go bezpośrednio
                        $paymentUrl = (string) $paymentToken;
                    }
                }
            } catch (\Throwable $e) {
                // Non-fatal: order was created; Flutter will receive payment_url=null
                // and should allow the user to retry payment from the order detail screen.
                Log::warning('Payment initiation after createOrder failed (non-fatal)', [
                    'guid'         => $data->guid,
                    'contracts_id' => $crmResponse->contractsId,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        // ── Step 8: CRM success — persist response ────────────────────────────
        $orderRequest->update([
            'status'               => OrderRequest::STATUS_CRM_SUCCESS,
            'crm_response_json'    => $crmResponse->raw,
            'crm_contracts_id'     => $crmResponse->contractsId,
            'crm_users_products_id'=> $crmResponse->usersProductsId,
            'crm_payments_id'      => $crmResponse->paymentsId,
            'payment_token'        => $paymentToken,
            'payment_url'          => $paymentUrl,
            'locked_at'            => null,
        ]);

        try {
            $offerType = $orderRequest->payload_json['offerType'] ?? $orderRequest->payload_json['offer_type'] ?? 'course';
            $title = "Zgłoszenie zarejestrowane";
            $body = "Dziękujemy za złożenie zamówienia!";
            
            if ($offerType === 'camp') {
                $title = "Zgłoszenie na obóz!";
                $body = "Zarejestrowaliśmy Twoje zgłoszenie na obóz. Dziękujemy!";
            } elseif ($offerType === 'dayCamp') {
                $title = "Zgłoszenie na półkolonię!";
                $body = "Zarejestrowaliśmy Twoje zgłoszenie na półkolonię. Dziękujemy!";
            } elseif ($offerType === 'workshop') {
                $title = "Zgłoszenie na warsztaty!";
                $body = "Zarejestrowaliśmy Twoje zgłoszenie na warsztaty. Dziękujemy!";
            } elseif ($offerType === 'ticket') {
                $title = "Zakup biletu!";
                $body = "Twój bilet został wygenerowany pomyślnie.";
            } else {
                $title = "Zgłoszenie na kurs!";
                $body = "Zarejestrowaliśmy Twoje zgłoszenie na kurs. Dziękujemy!";
            }

            $recipientUserId = $data->payerUserId ?: $data->userId;
            
            app(\App\Services\FirebasePushService::class)->sendToUser(
                (int) $recipientUserId,
                $title,
                $body,
                'system'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send order push notification (non-fatal)', [
                'error' => $e->getMessage(),
                'guid'  => $data->guid,
            ]);
        }

        // ── Step 9: local sync ────────────────────────────────────────────────
        try {
            $this->syncService->syncFromCrmResponse($orderRequest);

            // ── Step 10: sync ok ──────────────────────────────────────────────
            $orderRequest->update([
                'status'       => OrderRequest::STATUS_LOCAL_SYNCED,
                'processed_at' => now(),
            ]);

            Log::info('Order created and synced', [
                'guid'             => $data->guid,
                'crm_contracts_id' => $orderRequest->crm_contracts_id,
            ]);
        } catch (LocalSyncValidationException $e) {
            // ── Step 11: sync fail — schedule retry ───────────────────────────
            $orderRequest->update([
                'status'        => OrderRequest::STATUS_LOCAL_SYNC_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Local sync failed after CRM success — dispatching retry', [
                'guid'  => $data->guid,
                'error' => $e->getMessage(),
            ]);

            SyncOrderJob::dispatch($orderRequest->id)
                ->onQueue('orders')
                ->delay(now()->addSeconds(15));
        } catch (\Throwable $e) {
            $orderRequest->update([
                'status'        => OrderRequest::STATUS_LOCAL_SYNC_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Unexpected local sync error — dispatching retry', [
                'guid'  => $data->guid,
                'error' => $e->getMessage(),
            ]);

            SyncOrderJob::dispatch($orderRequest->id)
                ->onQueue('orders')
                ->delay(now()->addSeconds(15));
        }

        // ── Step 12 ───────────────────────────────────────────────────────────
        return OrderResult::fromOrderRequest($orderRequest->fresh(), false);
    }
}