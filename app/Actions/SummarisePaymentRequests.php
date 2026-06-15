<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Role-scoped dashboard aggregates for a user (their own data for employees,
 * company-wide for finance). Returns the structure the API exposes under "data".
 */
class SummarisePaymentRequests
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(User $user): array
    {
        $countByStatus = $this->zeroFilled(0)
            ->merge($this->scoped($user)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'))
            ->map(fn ($value) => (int) $value);

        $eurByStatus = $this->zeroFilled(0.0)
            ->merge($this->scoped($user)->selectRaw('status, sum(converted_amount_eur) as aggregate')->groupBy('status')->pluck('aggregate', 'status'))
            ->map(fn ($value) => round((float) $value, 2));

        $eurByCurrency = $this->scoped($user)
            ->selectRaw('currency, sum(converted_amount_eur) as aggregate')
            ->groupBy('currency')
            ->pluck('aggregate', 'currency')
            ->map(fn ($value) => round((float) $value, 2));

        return [
            'total_count' => (int) $countByStatus->sum(),
            'total_eur' => round((float) $eurByStatus->sum(), 2),
            'count_by_status' => $countByStatus,
            'eur_by_status' => $eurByStatus,
            'eur_by_currency' => $eurByCurrency,
        ];
    }

    /**
     * A fresh, role-scoped query — Eloquent builders aren't reusable once a
     * groupBy aggregate has run, so each aggregation gets its own.
     *
     * @return Builder<PaymentRequest>
     */
    private function scoped(User $user): Builder
    {
        return PaymentRequest::query()->visibleTo($user);
    }

    /**
     * Every status seeded to a zero baseline so absent statuses still appear.
     *
     * @return Collection<string, int|float>
     */
    private function zeroFilled(int|float $zero): Collection
    {
        return collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $status) => [$status->value => $zero]);
    }
}
