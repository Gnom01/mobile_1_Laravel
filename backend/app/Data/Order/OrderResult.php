<?php

namespace App\Data\Order;

use App\Models\OrderRequest;

/**
 * Result returned by OrderApplicationService::createOrder().
 */
final class OrderResult
{
    /** @var string */
    public $guid;

    /** @var string */
    public $status;

    /** @var int|null */
    public $crmContractsId;

    /** @var int|null */
    public $crmPaymentsId;

    /** @var string|null */
    public $paymentToken;

    /** @var string|null */
    public $paymentUrl;

    /** @var bool */
    public $wasAlreadyProcessed;

    public function __construct(
        string $guid,
        string $status,
        ?int $crmContractsId,
        ?int $crmPaymentsId,
        ?string $paymentToken,
        ?string $paymentUrl,
        bool $wasAlreadyProcessed
    ) {
        $this->guid = $guid;
        $this->status = $status;
        $this->crmContractsId = $crmContractsId;
        $this->crmPaymentsId = $crmPaymentsId;
        $this->paymentToken = $paymentToken;
        $this->paymentUrl = $paymentUrl;
        $this->wasAlreadyProcessed = $wasAlreadyProcessed;
    }

    public static function fromOrderRequest(OrderRequest $req, bool $wasAlreadyProcessed = false): self
    {
        return new self(
            $req->guid,
            $req->status,
            $req->crm_contracts_id,
            $req->crm_payments_id,
            $req->payment_token,
            $req->payment_url,
            $wasAlreadyProcessed
        );
    }

    public function toArray(): array
    {
        return [
            'guid' => $this->guid,
            'status' => $this->status,
            'crm_contracts_id' => $this->crmContractsId,
            'crm_payments_id' => $this->crmPaymentsId,
            'payment_token' => $this->paymentToken,
            'payment_url' => $this->paymentUrl,
            'was_already_processed' => $this->wasAlreadyProcessed,
        ];
    }
}
