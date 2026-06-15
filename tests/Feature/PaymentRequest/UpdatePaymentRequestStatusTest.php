<?php

namespace Tests\Feature\PaymentRequest;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdatePaymentRequestStatusTest extends TestCase
{
    use RefreshDatabase;

    private function pendingRequest(): PaymentRequest
    {
        $owner = User::factory()->employee()->create();

        return PaymentRequest::factory()->for($owner)->forCurrency(Currency::BRL)->pending()->create();
    }

    public function test_finance_can_approve_a_pending_request(): void
    {
        $request = $this->pendingRequest();
        Sanctum::actingAs(User::factory()->finance()->create());

        $this->patchJson("/api/payment-requests/{$request->id}", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Approved->value);

        $this->assertSame(PaymentStatus::Approved, $request->fresh()->status);
    }

    public function test_finance_can_reject_a_pending_request(): void
    {
        $request = $this->pendingRequest();
        Sanctum::actingAs(User::factory()->finance()->create());

        $this->patchJson("/api/payment-requests/{$request->id}", ['status' => 'rejected'])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Rejected->value);

        $this->assertSame(PaymentStatus::Rejected, $request->fresh()->status);
    }

    public function test_a_regular_employee_cannot_approve_or_reject(): void
    {
        $owner = User::factory()->employee()->create();
        $request = PaymentRequest::factory()->for($owner)->forCurrency(Currency::BRL)->pending()->create();

        // Even the owner (an employee) may not decide their own request.
        Sanctum::actingAs($owner);

        $this->patchJson("/api/payment-requests/{$request->id}", ['status' => 'approved'])
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $request->fresh()->status);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function terminalStatuses(): array
    {
        return [
            'approved' => ['approved'],
            'rejected' => ['rejected'],
            'expired' => ['expired'],
        ];
    }

    #[DataProvider('terminalStatuses')]
    public function test_a_request_that_is_not_pending_cannot_change_status(string $initial): void
    {
        $owner = User::factory()->employee()->create();
        $request = PaymentRequest::factory()->for($owner)->forCurrency(Currency::BRL)
            ->state(['status' => $initial])->create();

        Sanctum::actingAs(User::factory()->finance()->create());

        $this->patchJson("/api/payment-requests/{$request->id}", ['status' => 'approved'])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertSame(PaymentStatus::from($initial), $request->fresh()->status);
    }

    public function test_finance_cannot_decide_their_own_request(): void
    {
        $finance = User::factory()->finance()->create();
        $own = PaymentRequest::factory()->for($finance)->forCurrency(Currency::EUR)->pending()->create();

        Sanctum::actingAs($finance);

        // Four-eyes: a finance member may not approve a request they submitted.
        $this->patchJson("/api/payment-requests/{$own->id}", ['status' => 'approved'])
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $own->fresh()->status);
    }

    public function test_an_invalid_target_status_is_rejected_with_422(): void
    {
        $request = $this->pendingRequest();
        Sanctum::actingAs(User::factory()->finance()->create());

        // "pending" and "expired" are not valid finance decisions.
        $this->patchJson("/api/payment-requests/{$request->id}", ['status' => 'pending'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->patchJson("/api/payment-requests/{$request->id}", ['status' => 'banana'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_updating_a_missing_request_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->finance()->create());

        $this->patchJson('/api/payment-requests/999999', ['status' => 'approved'])->assertNotFound();
    }

    public function test_updating_requires_authentication(): void
    {
        $request = $this->pendingRequest();

        $this->patchJson("/api/payment-requests/{$request->id}", ['status' => 'approved'])
            ->assertUnauthorized();
    }
}
