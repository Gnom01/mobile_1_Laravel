<?php

namespace App\Exceptions\Order;

use RuntimeException;

/**
 * Thrown when the same guid is re-submitted with a different payload.
 * This is an idempotency violation — the caller must not change the payload
 * while using the same guid.
 */
final class OrderIdempotencyConflictException extends RuntimeException
{
    public function __construct(string $guid)
    {
        parent::__construct(
            "Idempotency conflict: guid={$guid} was already submitted with a different payload."
        );
    }
}
