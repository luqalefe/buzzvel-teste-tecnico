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
        return [
            // Finance may only ever decide approved or rejected; pending/expired
            // are not valid decisions and are rejected as invalid input.
            'status' => [
                'required',
                'string',
                Rule::in([
                    PaymentStatus::Approved->value,
                    PaymentStatus::Rejected->value,
                ]),
            ],
        ];
    }
}
