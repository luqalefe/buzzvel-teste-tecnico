<?php

namespace Tests\Feature\PaymentRequest;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Exceptions\ImmutableAttributeException;
use App\Models\PaymentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_recorded_rate_cannot_be_changed_after_creation(): void
    {
        $request = PaymentRequest::factory()->forCurrency(Currency::BRL)->create();

        $this->expectException(ImmutableAttributeException::class);

        $request->update(['exchange_rate' => 999.0]);
    }

    public function test_the_rate_source_cannot_be_changed_after_creation(): void
    {
        $request = PaymentRequest::factory()->forCurrency(Currency::BRL)->create();

        $this->expectException(ImmutableAttributeException::class);

        $request->update(['rate_source' => 'tampered']);
    }

    public function test_the_rate_fetched_at_timestamp_cannot_be_changed_after_creation(): void
    {
        $request = PaymentRequest::factory()->forCurrency(Currency::BRL)->create();

        $this->expectException(ImmutableAttributeException::class);

        $request->update(['rate_fetched_at' => now()->addDay()]);
    }

    public function test_a_status_change_leaves_the_rate_snapshot_untouched(): void
    {
        $request = PaymentRequest::factory()->forCurrency(Currency::BRL)->create();
        $rate = $request->exchange_rate;
        $source = $request->rate_source;
        $fetchedAt = $request->rate_fetched_at;

        $request->transitionTo(PaymentStatus::Approved);
        $request->refresh();

        $this->assertEquals($rate, $request->exchange_rate);
        $this->assertSame($source, $request->rate_source);
        $this->assertTrue($fetchedAt->equalTo($request->rate_fetched_at));
        $this->assertSame(PaymentStatus::Approved, $request->status);
    }

    public function test_a_request_keeps_its_original_rate_even_after_the_market_moves(): void
    {
        $request = PaymentRequest::factory()->forCurrency(Currency::BRL)->create([
            'exchange_rate' => 5.0,
            'converted_amount_eur' => 20.0,
            'amount' => 100.0,
        ]);

        // Time passes, the market moves — but a re-read still shows the snapshot.
        $fresh = PaymentRequest::findOrFail($request->id);

        $this->assertEquals(5.0, (float) $fresh->exchange_rate);
        $this->assertEquals(20.0, (float) $fresh->converted_amount_eur);
    }
}
