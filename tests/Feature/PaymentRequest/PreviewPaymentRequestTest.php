<?php

namespace Tests\Feature\PaymentRequest;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreviewPaymentRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_previews_a_conversion_without_persisting_anything(): void
    {
        Http::fake([
            'v6.exchangerate-api.com/*' => Http::response(['result' => 'success', 'conversion_rate' => 5.5]),
        ]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/payment-requests/preview?amount=110&currency=BRL')->assertOk();

        $response->assertJsonPath('data.currency', 'BRL')
            ->assertJsonPath('data.rate_source', 'exchangerate-api.com');

        $data = $response->json('data');
        $this->assertEqualsWithDelta(5.5, $data['exchange_rate'], 0.0001);
        $this->assertEqualsWithDelta(20.0, $data['converted_amount_eur'], 0.0001);

        $this->assertDatabaseCount('payment_requests', 0);
    }

    public function test_the_base_currency_preview_needs_no_provider_call(): void
    {
        Http::fake();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/payment-requests/preview?amount=250&currency=EUR')->assertOk();

        $data = $response->json('data');
        $this->assertEqualsWithDelta(1.0, $data['exchange_rate'], 0.0001);
        $this->assertEqualsWithDelta(250.0, $data['converted_amount_eur'], 0.0001);

        Http::assertNothingSent();
    }

    public function test_it_validates_the_query_parameters(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/payment-requests/preview?amount=0&currency=BRL')
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->getJson('/api/payment-requests/preview?amount=10&currency=XYZ')
            ->assertStatus(422)
            ->assertJsonValidationErrors('currency');
    }

    public function test_it_returns_503_when_the_provider_is_unavailable(): void
    {
        Http::fake(['v6.exchangerate-api.com/*' => Http::response('', 500)]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/payment-requests/preview?amount=10&currency=BRL')->assertStatus(503);
    }

    public function test_preview_requires_authentication(): void
    {
        $this->getJson('/api/payment-requests/preview?amount=10&currency=BRL')->assertUnauthorized();
    }
}
