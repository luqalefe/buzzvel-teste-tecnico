<?php

namespace Database\Seeders;

use App\Enums\Currency;
use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $password = Hash::make('password');

        // ── Finance team member (password: "password") ────────────────────
        $finance = User::factory()->finance()->create([
            'name' => 'Fiona Finance',
            'email' => 'finance@buzzvel.test',
            'password' => $password,
            'country' => 'Portugal',
            'currency' => Currency::EUR,
        ]);

        // ── Employees across different countries & currencies (≥5) ─────────
        $employees = [
            ['Alice Moreira', 'employee@buzzvel.test', 'Brazil', Currency::BRL],
            ['Kenji Tanaka', 'kenji@buzzvel.test', 'Japan', Currency::JPY],
            ['Hannah Clarke', 'hannah@buzzvel.test', 'United Kingdom', Currency::GBP],
            ['Lukas Müller', 'lukas@buzzvel.test', 'Germany', Currency::EUR],
            ['Zofia Kowalska', 'zofia@buzzvel.test', 'Poland', Currency::PLN],
            ['Astrid Lindholm', 'astrid@buzzvel.test', 'Sweden', Currency::SEK],
        ];

        foreach ($employees as [$name, $email, $country, $currency]) {
            $user = User::factory()->employee()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'country' => $country,
                'currency' => $currency,
            ]);

            // A spread of statuses so the dashboards and lists have data.
            PaymentRequest::factory()->for($user)->forCurrency($currency)->count(3)->create();
            PaymentRequest::factory()->for($user)->forCurrency($currency)->approved()->count(2)->create();
            PaymentRequest::factory()->for($user)->forCurrency($currency)->rejected()->create();
        }

        // ── Edge cases for the 48h expiry rule ────────────────────────────
        $alice = User::where('email', 'employee@buzzvel.test')->firstOrFail();
        PaymentRequest::factory()->for($alice)->forCurrency(Currency::BRL)->createdHoursAgo(47)->create(); // still pending
        PaymentRequest::factory()->for($alice)->forCurrency(Currency::BRL)->expired()->createdHoursAgo(72)->create();

        // Finance members can submit requests too.
        PaymentRequest::factory()->for($finance)->forCurrency(Currency::EUR)->count(2)->create();
    }
}
