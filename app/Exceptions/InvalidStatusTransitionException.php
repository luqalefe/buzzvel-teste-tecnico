<?php

namespace App\Exceptions;

use App\Enums\PaymentStatus;
use RuntimeException;

/**
 * Thrown when a payment request is asked to move out of a terminal status
 * (e.g. approving an already-rejected or expired request). Mapped to HTTP 422.
 */
class InvalidStatusTransitionException extends RuntimeException
{
    public function __construct(
        public readonly PaymentStatus $from,
        public readonly PaymentStatus $to,
    ) {
        parent::__construct(
            "Cannot transition a payment request from [{$from->value}] to [{$to->value}]; only pending requests can change status."
        );
    }
}
