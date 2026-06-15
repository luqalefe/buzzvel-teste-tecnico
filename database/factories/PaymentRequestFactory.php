<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentRequest>
 */
class PaymentRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var Currency $currency */
        $currency = fake()->randomElement(Currency::cases());
        $isBase = $currency === Currency::EUR;

        $amount = fake()->randomFloat(2, 10, 10_000);
        $rate = $isBase ? 1.0 : fake()->randomFloat(6, 0.4, 6.5);
        $converted = $isBase ? $amount : round($amount / $rate, 2);

        return [
            'user_id' => User::factory(),
            'amount' => $amount,
            'currency' => $currency,
            'exchange_rate' => $rate,
            'rate_source' => $isBase ? 'system' : config('exchange.provider.source'),
            'rate_fetched_at' => now(),
            'converted_amount_eur' => $converted,
            'status' => PaymentStatus::Pending,
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Pending]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Approved]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Rejected]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Expired]);
    }

    public function forCurrency(Currency $currency): static
    {
        $isBase = $currency === Currency::EUR;

        return $this->state(function (array $attributes) use ($currency, $isBase) {
            // Generate a fresh rate for the target currency rather than reusing
            // whatever the base definition rolled (which may have been EUR=1.0).
            $rate = $isBase ? 1.0 : fake()->randomFloat(6, 0.4, 6.5);
            $amount = (float) $attributes['amount'];

            return [
                'currency' => $currency,
                'exchange_rate' => $rate,
                'rate_source' => $isBase ? 'system' : config('exchange.provider.source'),
                'converted_amount_eur' => $isBase ? $amount : round($amount / $rate, 2),
            ];
        });
    }

    /**
     * Backdate the request so age-based rules (48h expiration) can be exercised.
     */
    public function createdHoursAgo(int $hours): static
    {
        return $this->state(fn () => [
            'created_at' => now()->subHours($hours),
            'updated_at' => now()->subHours($hours),
        ]);
    }
}
