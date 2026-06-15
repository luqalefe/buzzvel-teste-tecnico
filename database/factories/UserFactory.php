<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'country' => fake()->country(),
            'currency' => fake()->randomElement(Currency::cases()),
            'role' => Role::Employee,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A finance-team member.
     */
    public function finance(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Finance,
        ]);
    }

    /**
     * A regular employee.
     */
    public function employee(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Employee,
        ]);
    }

    /**
     * Force a specific local currency.
     */
    public function currency(Currency $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
        ]);
    }
}
