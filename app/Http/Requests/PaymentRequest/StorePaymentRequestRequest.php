<?php

namespace App\Http\Requests\PaymentRequest;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Present, strictly positive, at most 2 decimals, and a plain decimal
            // form (decimal:0,2 rejects scientific notation / stray whitespace,
            // which would otherwise reach bcmath and 500).
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999999999.99'],
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
