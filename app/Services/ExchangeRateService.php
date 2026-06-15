<?php

namespace App\Services;

use App\Enums\Currency;
use App\Exceptions\ExchangeRateUnavailableException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches EUR → local exchange rates from the configured provider
 * (exchangerate-api.com). The base currency short-circuits without any network
 * call; any provider failure surfaces as a domain exception so callers never
 * persist a request against a missing rate.
 */
class ExchangeRateService
{
    public function fetch(Currency $currency): ExchangeRate
    {
        // The base currency needs no conversion and no external call.
        if ($currency->isBase()) {
            return new ExchangeRate(1.0, 'system', Carbon::now());
        }

        $config = config('exchange.provider');

        try {
            // exchangerate-api.com pair endpoint: /{key}/pair/EUR/{currency}
            $response = Http::baseUrl($config['base_url'])
                ->timeout($config['timeout'])
                ->acceptJson()
                ->get(sprintf('/%s/pair/%s/%s', $config['key'], Currency::BASE->value, $currency->value));
        } catch (Throwable $exception) {
            throw new ExchangeRateUnavailableException($currency->value, $exception);
        }

        $payload = $response->failed() ? null : $response->json();

        if (! is_array($payload) || ($payload['result'] ?? null) !== 'success') {
            throw new ExchangeRateUnavailableException($currency->value);
        }

        $rate = $payload['conversion_rate'] ?? null;

        // A non-positive or non-numeric rate is unusable (and would divide by zero).
        if (! is_numeric($rate) || (float) $rate <= 0) {
            throw new ExchangeRateUnavailableException($currency->value);
        }

        return new ExchangeRate((float) $rate, $config['source'], Carbon::now());
    }
}
