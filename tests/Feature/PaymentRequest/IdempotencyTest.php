<?php

namespace Tests\Feature\PaymentRequest;

use App\Enums\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function fakeRate(float $rate): void
    {
        Http::fake([
            'v6.exchangerate-api.com/*' => Http::response(['result' => 'success', 'conversion_rate' => $rate]),
        ]);
    }

    private function actAsEmployee(): void
    {
        Sanctum::actingAs(User::factory()->employee()->currency(Currency::BRL)->create());
    }

    public function test_replaying_the_same_idempotency_key_returns_the_same_request_without_duplicating(): void
    {
        $this->fakeRate(5.5);
        $this->actAsEmployee();
        $payload = ['amount' => 100, 'currency' => 'BRL'];

        $first = $this->withHeader('Idempotency-Key', 'abc-123')->postJson('/api/payment-requests', $payload)->assertCreated();
        $second = $this->withHeader('Idempotency-Key', 'abc-123')->postJson('/api/payment-requests', $payload)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('payment_requests', 1);
    }

    public function test_same_key_with_a_different_payload_conflicts(): void
    {
        $this->fakeRate(5.5);
        $this->actAsEmployee();

        $this->withHeader('Idempotency-Key', 'k1')->postJson('/api/payment-requests', ['amount' => 100, 'currency' => 'BRL'])->assertCreated();
        $this->withHeader('Idempotency-Key', 'k1')->postJson('/api/payment-requests', ['amount' => 200, 'currency' => 'BRL'])->assertStatus(409);

        $this->assertDatabaseCount('payment_requests', 1);
    }

    public function test_without_a_key_each_request_creates_a_new_one(): void
    {
        $this->fakeRate(5.5);
        $this->actAsEmployee();

        $this->postJson('/api/payment-requests', ['amount' => 100, 'currency' => 'BRL'])->assertCreated();
        $this->postJson('/api/payment-requests', ['amount' => 100, 'currency' => 'BRL'])->assertCreated();

        $this->assertDatabaseCount('payment_requests', 2);
    }

    public function test_a_failed_provider_response_is_not_cached_so_the_key_can_be_retried(): void
    {
        // Provider is down on the first call, then recovers on the retry.
        Http::fakeSequence('v6.exchangerate-api.com/*')
            ->push('', 500)
            ->push(['result' => 'success', 'conversion_rate' => 5.5]);

        $this->actAsEmployee();
        $payload = ['amount' => 100, 'currency' => 'BRL'];

        $this->withHeader('Idempotency-Key', 'retry-1')->postJson('/api/payment-requests', $payload)->assertStatus(503);
        $this->assertDatabaseCount('payment_requests', 0);

        // The same key is NOT locked to the failed 503 — the retry succeeds.
        $this->withHeader('Idempotency-Key', 'retry-1')->postJson('/api/payment-requests', $payload)->assertCreated();
        $this->assertDatabaseCount('payment_requests', 1);
    }
}
