<?php

namespace App\Http\Requests\PaymentRequest\Concerns;

use App\Enums\Currency;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for the amount + currency pair, so the create and
 * preview endpoints can never drift apart in what they accept.
 */
trait HasConversionRules
{
    /**
     * @return array<string, mixed>
     */
    protected function conversionRules(): array
    {
        return [
            // Present, strictly positive, at most 2 decimals, plain decimal form
            // (rejects scientific notation / whitespace before it reaches bcmath).
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999999999.99'],
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
        ];
    }
}
