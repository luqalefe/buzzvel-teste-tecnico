<?php

namespace App\Http\Requests\PaymentRequest;

use App\Http\Requests\PaymentRequest\Concerns\HasConversionRules;
use Illuminate\Foundation\Http\FormRequest;

class PreviewPaymentRequestRequest extends FormRequest
{
    use HasConversionRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->conversionRules();
    }
}
