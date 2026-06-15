<?php

namespace Tests\Feature\PaymentRequest;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Models\PaymentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpirePaymentRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function request(PaymentStatus $status, int $hoursAgo): PaymentRequest
    {
        return PaymentRequest::factory()
            ->forCurrency(Currency::BRL)
            ->state(['status' => $status])
            ->createdHoursAgo($hoursAgo)
            ->create();
    }

    public function test_pending_requests_older_than_48h_are_expired(): void
    {
        $stale = $this->request(PaymentStatus::Pending, 49);

        $this->artisan('payment-requests:expire')->assertSuccessful();

        $this->assertSame(PaymentStatus::Expired, $stale->fresh()->status);
    }

    public function test_pending_requests_within_48h_stay_pending(): void
    {
        $fresh = $this->request(PaymentStatus::Pending, 47);

        $this->artisan('payment-requests:expire')->assertSuccessful();

        $this->assertSame(PaymentStatus::Pending, $fresh->fresh()->status);
    }

    public function test_already_decided_requests_are_never_touched(): void
    {
        $approved = $this->request(PaymentStatus::Approved, 100);
        $rejected = $this->request(PaymentStatus::Rejected, 100);

        $this->artisan('payment-requests:expire')->assertSuccessful();

        $this->assertSame(PaymentStatus::Approved, $approved->fresh()->status);
        $this->assertSame(PaymentStatus::Rejected, $rejected->fresh()->status);
    }

    public function test_the_command_reports_the_number_of_expired_requests(): void
    {
        $this->request(PaymentStatus::Pending, 50);
        $this->request(PaymentStatus::Pending, 72);
        $this->request(PaymentStatus::Pending, 10); // not stale

        $this->artisan('payment-requests:expire')
            ->expectsOutputToContain('Expired 2 ')
            ->assertSuccessful();
    }

    public function test_a_request_exactly_at_the_48h_mark_is_not_yet_expired(): void
    {
        // Freeze time so "exactly 48h" is deterministic (the rule is strictly >48h).
        $this->freezeTime();

        $boundary = $this->request(PaymentStatus::Pending, 48);

        $this->artisan('payment-requests:expire')->assertSuccessful();

        $this->assertSame(PaymentStatus::Pending, $boundary->fresh()->status);
    }

    public function test_expiring_preserves_the_immutable_rate_snapshot(): void
    {
        $stale = $this->request(PaymentStatus::Pending, 60);
        $rate = $stale->exchange_rate;
        $source = $stale->rate_source;

        $this->artisan('payment-requests:expire')->assertSuccessful();

        $fresh = $stale->fresh();
        $this->assertSame(PaymentStatus::Expired, $fresh->status);
        $this->assertEquals($rate, $fresh->exchange_rate);
        $this->assertSame($source, $fresh->rate_source);
    }
}
