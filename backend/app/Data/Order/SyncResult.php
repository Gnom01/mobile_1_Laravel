<?php

namespace App\Data\Order;

/**
 * Result returned by OrderSyncService::syncFromCrmResponse().
 */
final class SyncResult
{
    /** @var bool */
    public $success;

    /** @var int */
    public $contractsId;

    /** @var int */
    public $scheduleCount;

    /** @var int */
    public $paymentsCount;

    /** @var string */
    public $guid;

    public function __construct(
        bool $success,
        int $contractsId,
        int $scheduleCount,
        int $paymentsCount,
        string $guid
    ) {
        $this->success = $success;
        $this->contractsId = $contractsId;
        $this->scheduleCount = $scheduleCount;
        $this->paymentsCount = $paymentsCount;
        $this->guid = $guid;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'contracts_id' => $this->contractsId,
            'schedule_count' => $this->scheduleCount,
            'payments_count' => $this->paymentsCount,
            'guid' => $this->guid,
        ];
    }
}
