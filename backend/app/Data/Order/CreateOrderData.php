<?php

namespace App\Data\Order;

/**
 * Input DTO for OrderApplicationService::createOrder().
 * Carries everything the front-end sends plus the resolved user context.
 */
final class CreateOrderData
{
    /** @var string */
    public $guid;

    /** @var int */
    public $userId;

    /** @var int */
    public $payerUserId;

    /** @var array */
    public $payload;

    public function __construct(string $guid, int $userId, int $payerUserId, array $payload)
    {
        $this->guid = $guid;
        $this->userId = $userId;
        $this->payerUserId = $payerUserId;
        $this->payload = $payload;
    }

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            $data['guid'],
            $userId,
            (int) ($data['payerUserId'] ?? $userId),
            $data
        );
    }
}
