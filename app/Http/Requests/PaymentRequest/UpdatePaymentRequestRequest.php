<?php

namespace App\Http\Requests\PaymentRequest;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequestRequest extends FormRequest
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
        // The whitelist is derived from the enum's single source of truth:
        // finance may only ever decide approved or rejected.
        return [
            'status' => [
                'required',
                'string',
                Rule::in(array_map(fn (PaymentStatus $status) => $status->value, PaymentStatus::financeDecisions())),
            ],
        ];
    }
}
