<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Immutable snapshot of an exchange rate at a moment in time.
 *
 * `rate` is the price of one unit of the base currency (EUR) expressed in the
 * foreign currency — i.e. EUR → local. Converting a local amount back into the
 * base currency therefore divides by the rate.
 */
final class ExchangeRate
{
    public function __construct(
        public readonly float $rate,
        public readonly string $source,
        public readonly Carbon $fetchedAt,
    ) {}

    /**
     * Convert an amount expressed in the foreign currency into the base
     * currency, rounded to the base currency's minor unit (2 decimals).
     *
     * Uses arbitrary-precision (bcmath) arithmetic so large amounts and many-
     * decimal rates do not accumulate IEEE-754 float error.
     */
    public function convertToBase(int|float|string $amount): string
    {
        if ($this->rate <= 0) {
            throw new \DomainException('Cannot convert with a non-positive exchange rate.');
        }

        $rateStr = sprintf('%.10F', $this->rate);

        return self::bcround(bcdiv(self::normalizeAmount($amount), $rateStr, 12), 2);
    }

    /**
     * Coerce an amount into a plain decimal string bcmath can consume. Valid
     * decimals are used verbatim (full precision); anything else (scientific
     * notation, stray whitespace, junk) falls back to a float-formatted value
     * rather than blowing up bcdiv with a ValueError.
     */
    private static function normalizeAmount(int|float|string $amount): string
    {
        $value = is_string($amount) ? trim($amount) : (string) $amount;

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return sprintf('%.4F', (float) $amount);
        }

        return $value;
    }

    /**
     * bcmath lacks rounding (it truncates); add half a minor unit then truncate
     * to emulate round-half-up at the given precision.
     */
    private static function bcround(string $number, int $precision): string
    {
        $factor = '0.'.str_repeat('0', $precision).'5';

        $adjusted = str_starts_with($number, '-')
            ? bcsub($number, $factor, $precision + 1)
            : bcadd($number, $factor, $precision + 1);

        return bcdiv($adjusted, '1', $precision);
    }
}
