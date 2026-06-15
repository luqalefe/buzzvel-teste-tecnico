<?php

namespace Tests\Feature\PaymentRequest;

use App\Enums\Currency;
use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListPaymentRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_employee_sees_only_their_own_requests(): void
    {
        $me = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();

        $mine = PaymentRequest::factory()->for($me)->forCurrency(Currency::BRL)->count(3)->create();
        PaymentRequest::factory()->for($other)->forCurrency(Currency::USD)->count(2)->create();

        Sanctum::actingAs($me);

        $response = $this->getJson('/api/payment-requests')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing($mine->pluck('id')->all(), $ids);
    }

    public function test_results_can_be_filtered_by_status(): void
    {
        $me = User::factory()->employee()->create();
        PaymentRequest::factory()->for($me)->forCurrency(Currency::BRL)->pending()->count(2)->create();
        PaymentRequest::factory()->for($me)->forCurrency(Currency::BRL)->approved()->create();

        Sanctum::actingAs($me);

        $response = $this->getJson('/api/payment-requests?status=pending')->assertOk();

        $this->assertCount(2, $response->json('data'));
        foreach ($response->json('data') as $row) {
            $this->assertSame('pending', $row['status']);
        }
    }

    public function test_an_unknown_status_filter_is_rejected_with_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/payment-requests?status=banana')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_finance_can_see_requests_from_every_user(): void
    {
        $finance = User::factory()->finance()->create();
        $alice = User::factory()->employee()->create();
        $bob = User::factory()->employee()->create();

        $aliceReq = PaymentRequest::factory()->for($alice)->forCurrency(Currency::BRL)->create();
        $bobReq = PaymentRequest::factory()->for($bob)->forCurrency(Currency::USD)->create();

        Sanctum::actingAs($finance);

        $ids = collect($this->getJson('/api/payment-requests')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertContains($aliceReq->id, $ids);
        $this->assertContains($bobReq->id, $ids);
    }

    public function test_listing_requires_authentication(): void
    {
        $this->getJson('/api/payment-requests')->assertUnauthorized();
    }
}
