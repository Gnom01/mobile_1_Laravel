<?php

namespace App\Data\Order;

/**
 * Full snapshot returned by CRM fetchOrderByContractId().
 * Used for re-sync (retry jobs) without re-creating the order.
 */
final class CrmOrderSnapshot
{
    /** @var int */
    public $contractsId;

    /** @var array */
    public $contract;

    /** @var array */
    public $usersProducts;

    /** @var array */
    public $paymentsSchedules;

    /** @var array */
    public $usersBaskets;

    /** @var array */
    public $payments;

    /** @var array */
    public $paymentsItems;

    /** @var array */
    public $raw;

    public function __construct(
        int $contractsId,
        array $contract,
        array $usersProducts,
        array $paymentsSchedules,
        array $usersBaskets,
        array $payments,
        array $paymentsItems,
        array $raw
    ) {
        $this->contractsId = $contractsId;
        $this->contract = $contract;
        $this->usersProducts = $usersProducts;
        $this->paymentsSchedules = $paymentsSchedules;
        $this->usersBaskets = $usersBaskets;
        $this->payments = $payments;
        $this->paymentsItems = $paymentsItems;
        $this->raw = $raw;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['contractsID'] ?? $data['contractsId'] ?? 0),
            $data['contract'] ?? [],
            $data['usersProducts'] ?? [],
            $data['usersPaymentsSchedules'] ?? [],
            $data['usersBaskets'] ?? [],
            $data['payments'] ?? [],
            $data['paymentsItems'] ?? [],
            $data
        );
    }
}
