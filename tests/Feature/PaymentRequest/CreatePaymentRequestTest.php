<?php

namespace Tests\Feature\PaymentRequest;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreatePaymentRequestTest extends TestCase
{
    use RefreshDatabase;

    private function fakeRate(string $currency, float $rate): void
    {
        Http::fake([
            'v6.exchangerate-api.com/*' => Http::response([
                'result' => 'success',
                'base_code' => 'EUR',
                'target_code' => $currency,
                'conversion_rate' => $rate,
            ]),
        ]);
    }

    public function test_an_authenticated_employee_can_create_a_request_with_an_automatic_conversion(): void
    {
        $this->fakeRate('BRL', 5.5);
        $user = User::factory()->employee()->currency(Currency::BRL)->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payment-requests', [
            'amount' => 110,
            'currency' => 'BRL',
            'description' => 'Conference travel',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', PaymentStatus::Pending->value)
            ->assertJsonPath('data.currency', 'BRL')
            ->assertJsonPath('data.rate_source', 'exchangerate-api.com')
            ->assertJsonStructure([
                'data' => ['id', 'amount', 'currency', 'exchange_rate', 'rate_source', 'rate_fetched_at', 'converted_amount_eur', 'status', 'expires_at'],
            ]);

        // Loose numeric comparison: JSON serialises whole-number floats without
        // a decimal point (5.5 stays 5.5, but 20.0 → 20).
        $data = $response->json('data');
        $this->assertEqualsWithDelta(5.5, $data['exchange_rate'], 0.0001);
        $this->assertEqualsWithDelta(20.0, $data['converted_amount_eur'], 0.0001); // 110 / 5.5

        $this->assertDatabaseHas('payment_requests', [
            'user_id' => $user->id,
            'currency' => 'BRL',
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_a_request_in_the_base_currency_skips_the_provider_and_uses_rate_one(): void
    {
        Http::fake(); // record any outgoing call
        $user = User::factory()->currency(Currency::EUR)->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payment-requests', [
            'amount' => 250,
            'currency' => 'EUR',
        ]);

        $response->assertCreated();

        $data = $response->json('data');
        $this->assertEqualsWithDelta(1.0, $data['exchange_rate'], 0.0001);
        $this->assertEqualsWithDelta(250.0, $data['converted_amount_eur'], 0.0001);
        $this->assertEqualsWithDelta(250.0, $data['amount'], 0.0001);

        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'missing' => [null],
            'zero' => [0],
            'negative' => [-10],
            'scientific notation' => ['1e5'],
            'too many decimals' => ['10.123'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_it_rejects_an_invalid_amount_with_422(mixed $amount): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = ['currency' => 'BRL'];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        $this->postJson('/api/payment-requests', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('payment_requests', 0);
    }

    public function test_it_rejects_an_invalid_currency_with_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/payment-requests', ['amount' => 100, 'currency' => 'XYZ'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('currency');
    }

    public function test_when_the_provider_is_unavailable_it_returns_503_and_persists_nothing(): void
    {
        Http::fake(['v6.exchangerate-api.com/*' => Http::response('', 500)]);
        $user = User::factory()->employee()->currency(Currency::BRL)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/payment-requests', ['amount' => 100, 'currency' => 'BRL'])
            ->assertStatus(503)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('payment_requests', 0);
    }

    public function test_a_provider_connection_failure_returns_503_and_persists_nothing(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));
        $user = User::factory()->employee()->currency(Currency::BRL)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/payment-requests', ['amount' => 100, 'currency' => 'BRL'])
            ->assertStatus(503);

        $this->assertDatabaseCount('payment_requests', 0);
    }

    public function test_it_converts_the_request_currency_not_the_users_currency(): void
    {
        $this->fakeRate('JPY', 160.0);
        // User's own currency is EUR, but the request is in JPY.
        $user = User::factory()->employee()->currency(Currency::EUR)->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payment-requests', ['amount' => 16000, 'currency' => 'JPY'])
            ->assertCreated();

        $data = $response->json('data');
        $this->assertSame('JPY', $data['currency']);
        $this->assertEqualsWithDelta(160.0, $data['exchange_rate'], 0.0001);
        $this->assertEqualsWithDelta(100.0, $data['converted_amount_eur'], 0.0001); // 16000 / 160
    }

    public function test_it_requires_authentication(): void
    {
        $this->postJson('/api/payment-requests', ['amount' => 100, 'currency' => 'BRL'])
            ->assertUnauthorized();
    }
}
