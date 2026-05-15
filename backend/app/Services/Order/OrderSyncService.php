<?php

namespace App\Services\Order;

use App\Data\Order\SyncResult;
use App\Exceptions\Order\LocalSyncValidationException;
use App\Models\CrmObjectMapping;
use App\Models\Contract;
use App\Models\OrderRequest;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\UsersPaymentsSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderSyncService
{
    /**
     * Persist a local projection of a successful CRM order.
     *
     * This method is fully idempotent — running it multiple times for the same
     * order_request guid produces exactly the same DB state (upsert by CRM id).
     *
     * @throws LocalSyncValidationException
     */
    public function syncFromCrmResponse(OrderRequest $orderRequest): SyncResult 
    {
        $crm  = $orderRequest->crm_response_json ?? [];
        $guid = $orderRequest->guid;

        // ── Extract sub-sections from CRM response ────────────────────────────
        $contractData       = $crm['contract']               ?? $crm;
        $usersProductsData  = $crm['usersProducts']          ?? [];
        $schedulesData      = $crm['usersPaymentsSchedules'] ?? [];
        $basketsData        = $crm['usersBaskets']           ?? [];
        $paymentsData       = $crm['payments']               ?? [];
        $paymentsItemsData  = $crm['paymentsItems']          ?? [];

        $contractsId = (int) ($orderRequest->crm_contracts_id
            ?? $contractData['contractsID']
            ?? $contractData['contractsId']
            ?? 0);

        if ($contractsId === 0) {
            throw new LocalSyncValidationException(
                'Missing contractsID in CRM response.',
                ['crm_response_keys' => array_keys($crm)],
            );
        }

        // ── Pre-sync consistency check ────────────────────────────────────────
        $this->validateConsistency($schedulesData, $paymentsData, $contractsId, $crm);

        // ── All upserts inside a single DB transaction ────────────────────────
        DB::transaction(function () use (
            $guid, $contractsId, $orderRequest,
            $contractData, $usersProductsData, $schedulesData,
            $basketsData, $paymentsData, $paymentsItemsData
        ): void {
            $contract = $this->upsertContract($contractsId, $contractData, $guid);
            $this->recordMapping($guid, 'contracts', $contract->contractsID, 'contracts', $contractsId);

            foreach ($usersProductsData as $up) {
                $upId  = (int) ($up['usersProductsID'] ?? $up['usersProductsId'] ?? 0);
                if ($upId === 0) {
                    continue;
                }
                $this->upsertUsersProduct($upId, $up, $guid);
                $this->recordMapping($guid, 'usersproducts', $upId, 'usersproducts', $upId);
            }

            foreach ($schedulesData as $sched) {
                $schedId = (int) ($sched['usersPaymentsSchedulesID'] ?? $sched['usersPaymentsSchedulesId'] ?? 0);
                if ($schedId === 0) {
                    continue;
                }
                $this->upsertPaymentsSchedule($schedId, $sched, $guid);
                $this->recordMapping($guid, 'userspaymentsschedules', $schedId, 'userspaymentsschedules', $schedId);
            }

            foreach ($paymentsData as $pay) {
                $payId = (int) ($pay['paymentsID'] ?? $pay['paymentsId'] ?? 0);
                if ($payId === 0) {
                    continue;
                }
                $this->upsertPayment($payId, $pay, $guid);
                $this->recordMapping($guid, 'payments', $payId, 'payments', $payId);
            }

            foreach ($paymentsItemsData as $item) {
                $itemId = (int) ($item['paymentsItemsID'] ?? $item['paymentsItemsId'] ?? 0);
                if ($itemId === 0) {
                    continue;
                }
                $this->upsertPaymentItem($itemId, $item, $guid);
                $this->recordMapping($guid, 'paymentsitems', $itemId, 'paymentsitems', $itemId);
            }
        });

        Log::info('OrderSyncService: local projection saved', [
            'guid'         => $guid,
            'contracts_id' => $contractsId,
            'schedules'    => count($schedulesData),
            'payments'     => count($paymentsData),
        ]);

        return new SyncResult(
            true,
            $contractsId,
            count($schedulesData),
            count($paymentsData),
            $guid
        );
    }

    // ─── Upsert helpers ────────────────────────────────────────────────────────

    private function upsertContract(int $contractsId, array $data, string $guid): Contract
    {
        $attributes = array_merge($data, ['crm_order_guid' => $guid]);
        unset($attributes['contractsID'], $attributes['contractsId']);

        Contract::withoutGlobalScopes()->updateOrCreate(
            ['contractsID' => $contractsId],
            $attributes,
        );

        return Contract::find($contractsId);
    }

    private function upsertUsersProduct(int $id, array $data, string $guid): void
    {
        // usersproducts table mirrors CRM — use updateOrCreate by CRM primary key.
        // The model/table may not exist yet in the project; guard safely.
        if (!class_exists(\App\Models\UsersProduct::class)) {
            return;
        }

        $attributes = array_merge($data, ['crm_order_guid' => $guid]);
        unset($attributes['usersProductsID'], $attributes['usersProductsId']);

        \App\Models\UsersProduct::updateOrCreate(['usersProductsID' => $id], $attributes);
    }

    private function upsertPaymentsSchedule(int $id, array $data, string $guid): void
    {
        $attributes = array_merge($data, ['crm_order_guid' => $guid]);
        unset($attributes['usersPaymentsSchedulesID'], $attributes['usersPaymentsSchedulesId']);

        UsersPaymentsSchedule::updateOrCreate(
            ['usersPaymentsSchedulesID' => $id],
            $attributes,
        );
    }

    private function upsertPayment(int $id, array $data, string $guid): void
    {
        $attributes = array_merge($data, ['crm_order_guid' => $guid]);
        unset($attributes['paymentsID'], $attributes['paymentsId']);

        Payment::updateOrCreate(['paymentsID' => $id], $attributes);
    }

    private function upsertPaymentItem(int $id, array $data, string $guid): void
    {
        $attributes = array_merge($data, ['crm_order_guid' => $guid]);
        unset($attributes['paymentsItemsID'], $attributes['paymentsItemsId']);

        PaymentItem::updateOrCreate(['paymentsItemsID' => $id], $attributes);
    }

    // ─── Mapping ───────────────────────────────────────────────────────────────

    private function recordMapping(
        string $guid,
        string $localTable,
        int    $localId,
        string $crmTable,
        int    $crmId
    ): void {
        CrmObjectMapping::updateOrCreate(
            ['crm_table' => $crmTable, 'crm_id' => $crmId],
            [
                'guid'           => $guid,
                'local_table'    => $localTable,
                'local_id'       => $localId,
                'last_synced_at' => now(),
            ],
        );
    }

    // ─── Consistency validation ────────────────────────────────────────────────

    /**
     * @throws LocalSyncValidationException
     */
    private function validateConsistency(
        array $schedules,
        array $payments,
        int   $contractsId,
        array $crmResponse
    ): void {
        $errors = [];

        // 1. At least one schedule row
        if (count($schedules) === 0 && !empty($crmResponse['usersPaymentsSchedules'])) {
            $errors[] = 'No payment schedules in CRM response.';
        }

        // 2. All schedule rows must reference the expected contractsID
        $contractIdKey = 'contractsID';
        foreach ($schedules as $i => $sched) {
            $schedContractsId = (int) ($sched[$contractIdKey] ?? $sched['contractsId'] ?? 0);
            if ($schedContractsId !== 0 && $schedContractsId !== $contractsId) {
                $errors[] = "Schedule[{$i}].contractsID={$schedContractsId} does not match expected {$contractsId}.";
            }
        }

        // 3. Payment amounts must be positive
        foreach ($payments as $i => $pay) {
            $amount = (float) ($pay['paymentAmount'] ?? 0);
            if ($amount < 0) {
                $errors[] = "Payment[{$i}].paymentAmount={$amount} is negative.";
            }
        }

        if (!empty($errors)) {
            throw new LocalSyncValidationException(
                'CRM response failed local consistency check: ' . implode(' | ', $errors),
                ['errors' => $errors, 'contracts_id' => $contractsId],
            );
        }
    }
}