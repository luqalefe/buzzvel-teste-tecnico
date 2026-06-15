<?php

namespace Tests\Feature\PaymentRequest;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Models\PaymentRequest;
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

    private function pendingRequest(): PaymentRequest
    {
        $owner = User::factory()->employee()->create();

        return PaymentRequest::factory()->for($owner)->forCurrency(Currency::BRL)->pending()->create();
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

        // The replay is flagged and serves the stored response.
        $first->assertHeaderMissing('Idempotent-Replayed');
        $second->assertHeader('Idempotent-Replayed', 'true');

        // It skips the controller, so the exchange-rate provider is called once.
        Http::assertSentCount(1);
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

    public function test_an_idempotency_key_is_scoped_per_user_so_another_user_cannot_replay_the_stored_response(): void
    {
        $this->fakeRate(5.5);
        $payload = ['amount' => 100, 'currency' => 'BRL'];

        $userA = User::factory()->employee()->currency(Currency::BRL)->create();
        $userB = User::factory()->employee()->currency(Currency::BRL)->create();

        Sanctum::actingAs($userA);
        $first = $this->withHeader('Idempotency-Key', 'shared')->postJson('/api/payment-requests', $payload)->assertCreated();

        // Same literal key, different user: this is user B's own request, not a replay of A's.
        Sanctum::actingAs($userB);
        $second = $this->withHeader('Idempotency-Key', 'shared')->postJson('/api/payment-requests', $payload)->assertCreated();

        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseHas('payment_requests', ['id' => $second->json('data.id'), 'user_id' => $userB->id]);
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

    // ── PATCH (approve/reject) idempotency ──────────────────────────────────

    public function test_replaying_an_approval_with_the_same_key_replays_the_decision_without_erroring(): void
    {
        $request = $this->pendingRequest();
        Sanctum::actingAs(User::factory()->finance()->create());

        $first = $this->withHeader('Idempotency-Key', 'patch-1')
            ->patchJson("/api/payment-requests/{$request->id}", ['status' => 'approved'])
            ->assertOk();

        // Without idempotency a repeat would 422 (no longer pending); here it
        // replays the original 200 decision.
        $second = $this->withHeader('Idempotency-Key', 'patch-1')
            ->patchJson("/api/payment-requests/{$request->id}", ['status' => 'approved'])
            ->assertOk();

        $first->assertHeaderMissing('Idempotent-Replayed');
        $second->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(PaymentStatus::Approved, $request->fresh()->status);
    }

    public function test_the_same_patch_key_with_a_different_decision_conflicts(): void
    {
        $request = $this->pendingRequest();
        Sanctum::actingAs(User::factory()->finance()->create());

        $this->withHeader('Idempotency-Key', 'patch-2')
            ->patchJson("/api/payment-requests/{$request->id}", ['status' => 'approved'])->assertOk();

        $this->withHeader('Idempotency-Key', 'patch-2')
            ->patchJson("/api/payment-requests/{$request->id}", ['status' => 'rejected'])->assertStatus(409);

        $this->assertSame(PaymentStatus::Approved, $request->fresh()->status);
    }

    public function test_a_patch_key_cannot_be_reused_for_a_different_request(): void
    {
        $a = $this->pendingRequest();
        $b = $this->pendingRequest();
        Sanctum::actingAs(User::factory()->finance()->create());

        $this->withHeader('Idempotency-Key', 'patch-3')
            ->patchJson("/api/payment-requests/{$a->id}", ['status' => 'approved'])->assertOk();

        // Same key, different resource (different path) → conflict, B untouched.
        $this->withHeader('Idempotency-Key', 'patch-3')
            ->patchJson("/api/payment-requests/{$b->id}", ['status' => 'approved'])->assertStatus(409);

        $this->assertSame(PaymentStatus::Pending, $b->fresh()->status);
    }
}
