<?php

namespace App\Exceptions\Order;

use RuntimeException;

/**
 * Thrown by OrderSyncService when the CRM response data fails consistency
 * checks (installment count mismatch, amount mismatch, etc.).
 */
final class LocalSyncValidationException extends RuntimeException
{
    /** @var array */
    public $details;

    public function __construct(
        string $message,
        array $details = [],
        ?\Throwable $previous = null
    ) {
        $this->details = $details;
        parent::__construct($message, 0, $previous);
    }
}
