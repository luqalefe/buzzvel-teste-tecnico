<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when code attempts to mutate an attribute that is immutable after
 * creation (the recorded exchange-rate snapshot of a payment request).
 */
class ImmutableAttributeException extends RuntimeException
{
    public function __construct(public readonly string $attribute)
    {
        parent::__construct("The attribute [{$attribute}] is immutable and cannot be changed after creation.");
    }
}
