<?php

namespace App\Exceptions\Order;

use RuntimeException;

/**
 * Thrown when CRM returns a server error (5xx) or is unreachable.
 * Treated as a transient integration failure; retry may be appropriate.
 */
final class CrmIntegrationException extends RuntimeException
{
    /** @var int */
    public $httpStatus;

    public function __construct(
        string $message,
        int $httpStatus = 0,
        ?\Throwable $previous = null
    ) {
        $this->httpStatus = $httpStatus;
        parent::__construct($message, 0, $previous);
    }
}
