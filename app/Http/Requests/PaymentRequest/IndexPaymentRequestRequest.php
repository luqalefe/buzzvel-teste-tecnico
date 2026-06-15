<?php

namespace App\Http\Requests\PaymentRequest;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPaymentRequestRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(PaymentStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
