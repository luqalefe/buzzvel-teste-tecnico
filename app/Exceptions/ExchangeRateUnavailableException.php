<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when the external exchange-rate provider cannot supply a rate
 * (network failure, error status, malformed/empty payload). Mapped to HTTP 503
 * so the caller knows the request was not persisted and can retry later.
 */
class ExchangeRateUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly string $currency,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "Unable to retrieve an exchange rate for [{$currency}] from the exchange-rate provider.",
            previous: $previous,
        );
    }
}
