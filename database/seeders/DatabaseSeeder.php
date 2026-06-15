<?php

namespace Database\Seeders;

use App\Enums\Currency;
use App\Enums\Role;
use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Idempotent seed: users are upserted by e-mail and the sample payment
     * requests are only created on an empty database. This lets the live
     * deployment run `migrate --seed` on every release without piling up data.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        // ── Finance team member (password: "password") ────────────────────
        $finance = User::firstOrCreate(
            ['email' => 'finance@buzzvel.test'],
            ['name' => 'Fiona Finance', 'password' => $password, 'country' => 'Portugal', 'currency' => Currency::EUR, 'role' => Role::Finance],
        );

        // ── Employees across different countries & currencies (≥5) ─────────
        $employees = [
            ['Alice Moreira', 'employee@buzzvel.test', 'Brazil', Currency::BRL],
            ['Kenji Tanaka', 'kenji@buzzvel.test', 'Japan', Currency::JPY],
            ['Hannah Clarke', 'hannah@buzzvel.test', 'United Kingdom', Currency::GBP],
            ['Lukas Müller', 'lukas@buzzvel.test', 'Germany', Currency::EUR],
            ['Zofia Kowalska', 'zofia@buzzvel.test', 'Poland', Currency::PLN],
            ['Astrid Lindholm', 'astrid@buzzvel.test', 'Sweden', Currency::SEK],
        ];

        $users = [];
        foreach ($employees as [$name, $email, $country, $currency]) {
            $users[] = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password, 'country' => $country, 'currency' => $currency, 'role' => Role::Employee],
            );
        }

        // Sample requests are demo-only: skip once the database already has data.
        if (PaymentRequest::query()->exists()) {
            return;
        }

        foreach ($users as $user) {
            $currency = $user->currency;

            // A spread of statuses so the dashboards and lists have data.
            PaymentRequest::factory()->for($user)->forCurrency($currency)->count(3)->create();
            PaymentRequest::factory()->for($user)->forCurrency($currency)->approved()->count(2)->create();
            PaymentRequest::factory()->for($user)->forCurrency($currency)->rejected()->create();
        }

        // ── Edge cases for the 48h expiry rule ────────────────────────────
        $alice = $users[0];
        PaymentRequest::factory()->for($alice)->forCurrency(Currency::BRL)->createdHoursAgo(47)->create(); // still pending
        PaymentRequest::factory()->for($alice)->forCurrency(Currency::BRL)->expired()->createdHoursAgo(72)->create();

        // Finance members can submit requests too.
        PaymentRequest::factory()->for($finance)->forCurrency(Currency::EUR)->count(2)->create();
    }
}
