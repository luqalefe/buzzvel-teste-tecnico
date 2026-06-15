<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\PaymentRequest;
use Illuminate\Database\Eloquent\Collection;

/**
 * Expire every pending request that has been waiting longer than the 48h TTL.
 * Returns the number of requests expired.
 */
class ExpireStalePaymentRequests
{
    public function __invoke(): int
    {
        $threshold = now()->subHours(PaymentRequest::PENDING_TTL_HOURS);

        $expired = 0;

        PaymentRequest::query()
            ->expirable($threshold)
            ->chunkById(500, function (Collection $requests) use (&$expired): void {
                foreach ($requests as $request) {
                    // transitionTo keeps the state-machine + immutability rules
                    // centralised (only pending → expired ever happens here).
                    $request->transitionTo(PaymentStatus::Expired);
                    $expired++;
                }
            });

        return $expired;
    }
}
