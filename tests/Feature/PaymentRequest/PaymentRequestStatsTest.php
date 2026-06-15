<?php

namespace Tests\Feature\PaymentRequest;

use App\Enums\Currency;
use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentRequestStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_employee_gets_aggregates_for_only_their_own_requests(): void
    {
        $me = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();

        PaymentRequest::factory()->for($me)->forCurrency(Currency::BRL)->pending()->count(2)->create();
        PaymentRequest::factory()->for($me)->forCurrency(Currency::BRL)->approved()->create();
        PaymentRequest::factory()->for($other)->forCurrency(Currency::USD)->pending()->count(5)->create();

        Sanctum::actingAs($me);

        $response = $this->getJson('/api/payment-requests/stats')->assertOk();

        $response->assertJsonPath('data.total_count', 3)
            ->assertJsonPath('data.count_by_status.pending', 2)
            ->assertJsonPath('data.count_by_status.approved', 1)
            ->assertJsonPath('data.count_by_status.rejected', 0)
            ->assertJsonPath('data.count_by_status.expired', 0);
    }

    public function test_finance_gets_aggregates_across_all_users(): void
    {
        $finance = User::factory()->finance()->create();
        PaymentRequest::factory()->forCurrency(Currency::BRL)->pending()->count(3)->create();
        PaymentRequest::factory()->forCurrency(Currency::JPY)->approved()->count(2)->create();

        Sanctum::actingAs($finance);

        $this->getJson('/api/payment-requests/stats')
            ->assertOk()
            ->assertJsonPath('data.total_count', 5)
            ->assertJsonStructure([
                'data' => ['total_count', 'total_eur', 'count_by_status', 'eur_by_status', 'eur_by_currency'],
            ]);
    }

    public function test_stats_does_not_collide_with_the_show_route(): void
    {
        // "stats" must resolve to the aggregates endpoint, not be treated as an id.
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/payment-requests/stats')->assertOk();
    }

    public function test_stats_requires_authentication(): void
    {
        $this->getJson('/api/payment-requests/stats')->assertUnauthorized();
    }
}
