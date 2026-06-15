<?php

namespace App\Http\Requests\Auth;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
            // `role` is intentionally not accepted: a visitor can only ever
            // register as an employee. Finance accounts are provisioned out of band.
        ];
    }
}
