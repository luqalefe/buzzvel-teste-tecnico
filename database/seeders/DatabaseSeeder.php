<?php

namespace Database\Seeders;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
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
     * Indicative units-per-EUR, used ONLY to build readable demo data. These are
     * not live rates — the app fetches real rates from exchangerate-api.com at
     * creation time; this map just makes the seeded amounts look plausible per
     * currency.
     *
     * @var array<string, float>
     */
    private const DEMO_RATES = [
        'EUR' => 1.0,
        'USD' => 1.08,
        'GBP' => 0.85,
        'BRL' => 6.20,
        'JPY' => 170.0,
        'CHF' => 0.94,
        'SEK' => 11.50,
        'PLN' => 4.30,
        'NOK' => 11.70,
        'DKK' => 7.46,
    ];

    /**
     * Idempotent seed: users are upserted by e-mail and the sample payment
     * requests are only created on an empty database, so it can run on every
     * deploy without piling up data. Deliberately Faker-free so it also works in
     * production (composer install --no-dev, where fakerphp/faker is absent).
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

        // A spread of statuses per employee so the dashboards and lists have data.
        foreach ($users as $i => $user) {
            $bump = $i * 30;
            $c = $user->currency;
            $this->seedRequest($user, $c, 120 + $bump, PaymentStatus::Pending, 2, 'Office supplies');
            $this->seedRequest($user, $c, 260 + $bump, PaymentStatus::Pending, 9, 'Client dinner');
            $this->seedRequest($user, $c, 540 + $bump, PaymentStatus::Pending, 22, null);
            $this->seedRequest($user, $c, 175 + $bump, PaymentStatus::Approved, 31, 'Conference ticket');
            $this->seedRequest($user, $c, 820 + $bump, PaymentStatus::Approved, 54, 'Software license');
            $this->seedRequest($user, $c, 300 + $bump, PaymentStatus::Rejected, 44, 'Out-of-policy expense');
        }

        // ── Edge cases for the 48h expiry rule ────────────────────────────
        $alice = $users[0];
        $this->seedRequest($alice, Currency::BRL, 160, PaymentStatus::Pending, 47, 'Almost expired (47h pending)');
        $this->seedRequest($alice, Currency::BRL, 240, PaymentStatus::Expired, 72, 'Swept by the 48h rule');

        // Finance members can submit requests too.
        $this->seedRequest($finance, Currency::EUR, 410, PaymentStatus::Pending, 5, 'Team lunch');
        $this->seedRequest($finance, Currency::EUR, 900, PaymentStatus::Approved, 60, 'Annual subscription');
    }

    /**
     * Create one demo payment request with an immutable rate snapshot, mirroring
     * what the real creation flow records — but with deterministic, Faker-free
     * values. `$targetEur` is the rough EUR value; the local amount is derived
     * from the indicative demo rate so the figures look currency-appropriate.
     */
    private function seedRequest(User $user, Currency $currency, float $targetEur, PaymentStatus $status, int $hoursAgo, ?string $description): void
    {
        $isBase = $currency === Currency::EUR;
        $rate = self::DEMO_RATES[$currency->value] ?? 1.0;
        $amount = $isBase ? $targetEur : round($targetEur * $rate, 2);
        $converted = $isBase ? $amount : round($amount / $rate, 2);
        $when = now()->subHours($hoursAgo);

        $request = new PaymentRequest;
        $request->forceFill([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => $currency,
            'exchange_rate' => $rate,
            'rate_source' => $isBase ? 'system' : config('exchange.provider.source'),
            'rate_fetched_at' => $when,
            'converted_amount_eur' => $converted,
            'status' => $status,
            'description' => $description,
            'created_at' => $when,
            'updated_at' => $when,
        ]);
        $request->save();
    }
}
