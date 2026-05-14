<?php

namespace App\Data\Order;

/**
 * Parsed response from CRM createOrder endpoint.
 */
final class CrmOrderResponse
{
    /** @var int */
    public $contractsId;

    /** @var int */
    public $usersProductsId;

    /** @var int */
    public $paymentsId;

    /** @var string|null */
    public $paymentToken;

    /** @var string|null */
    public $paymentUrl;

    /** @var array */
    public $raw;

    public function __construct(
        int $contractsId,
        int $usersProductsId,
        int $paymentsId,
        ?string $paymentToken,
        ?string $paymentUrl,
        array $raw
    ) {
        $this->contractsId = $contractsId;
        $this->usersProductsId = $usersProductsId;
        $this->paymentsId = $paymentsId;
        $this->paymentToken = $paymentToken;
        $this->paymentUrl = $paymentUrl;
        $this->raw = $raw;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['contractsID'] ?? $data['contractsId'] ?? 0),
            (int) ($data['usersProductsID'] ?? $data['usersProductsId'] ?? 0),
            (int) ($data['paymentsID'] ?? $data['paymentsId'] ?? 0),
            isset($data['paymentToken']) ? (string) $data['paymentToken'] : null,
            isset($data['paymentUrl']) ? (string) $data['paymentUrl'] : null,
            $data
        );
    }
}
