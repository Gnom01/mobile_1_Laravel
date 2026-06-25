<?php

namespace App\Exceptions\Order;

use RuntimeException;

/**
 * Thrown when createOrder is called for a guid that is already being processed
 * within the locking TTL.
 */
final class OrderAlreadyProcessingException extends RuntimeException
{
    public function __construct(string $guid)
    {
        parent::__construct("Order guid={$guid} is already being processed. Retry later.");
    }
}
