<?php

namespace Tests\Feature\PaymentRequest;

use App\Enums\Currency;
use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShowPaymentRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_can_view_their_request_with_all_fields(): void
    {
        $owner = User::factory()->employee()->create();
        $request = PaymentRequest::factory()->for($owner)->forCurrency(Currency::BRL)->create();

        Sanctum::actingAs($owner);

        $this->getJson("/api/payment-requests/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $request->id)
            ->assertJsonStructure([
                'data' => [
                    'id', 'amount', 'currency', 'exchange_rate', 'rate_source',
                    'rate_fetched_at', 'converted_amount_eur', 'status',
                    'description', 'expires_at', 'created_at',
                ],
            ]);
    }

    public function test_a_different_employee_cannot_view_someone_elses_request(): void
    {
        $owner = User::factory()->employee()->create();
        $intruder = User::factory()->employee()->create();
        $request = PaymentRequest::factory()->for($owner)->forCurrency(Currency::BRL)->create();

        Sanctum::actingAs($intruder);

        $this->getJson("/api/payment-requests/{$request->id}")->assertForbidden();
    }

    public function test_finance_can_view_any_request(): void
    {
        $owner = User::factory()->employee()->create();
        $finance = User::factory()->finance()->create();
        $request = PaymentRequest::factory()->for($owner)->forCurrency(Currency::BRL)->create();

        Sanctum::actingAs($finance);

        $this->getJson("/api/payment-requests/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $request->id);
    }

    public function test_a_missing_request_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/payment-requests/999999')->assertNotFound();
    }
}
