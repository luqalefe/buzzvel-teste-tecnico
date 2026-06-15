<?php

namespace App\Policies;

use App\Models\PaymentRequest;
use App\Models\User;

class PaymentRequestPolicy
{
    /**
     * A user may view a request if they own it or they are finance.
     */
    public function view(User $user, PaymentRequest $paymentRequest): bool
    {
        return $user->isFinance() || $paymentRequest->user_id === $user->id;
    }

    /**
     * Only finance may decide a request, and never their own submission
     * (segregation of duties / four-eyes principle).
     */
    public function update(User $user, PaymentRequest $paymentRequest): bool
    {
        return $user->isFinance() && $paymentRequest->user_id !== $user->id;
    }
}
