<?php

namespace App\Exceptions\Order;

use RuntimeException;

/**
 * Thrown when CRM returns a business error (4xx).
 * The order_request is marked crm_failed; no local projections are created.
 */
final class CrmOrderException extends RuntimeException
{
    /** @var int */
    public $httpStatus;

    /** @var array */
    public $crmErrors;

    public function __construct(
        string $message,
        int $httpStatus = 0,
        array $crmErrors = [],
        ?\Throwable $previous = null
    ) {
        $this->httpStatus = $httpStatus;
        $this->crmErrors = $crmErrors;
        parent::__construct($message, 0, $previous);
    }
}
