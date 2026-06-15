<?php

namespace Tests\Unit;

use App\Enums\Currency;
use App\Exceptions\ExchangeRateUnavailableException;
use App\Services\ExchangeRateService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateServiceTest extends TestCase
{
    private function service(): ExchangeRateService
    {
        return app(ExchangeRateService::class);
    }

    public function test_returns_rate_of_one_for_base_currency_without_calling_the_provider(): void
    {
        Http::fake();

        $rate = $this->service()->fetch(Currency::EUR);

        $this->assertSame(1.0, $rate->rate);
        $this->assertSame('20.00', $rate->convertToBase(20));

        Http::assertNothingSent();
    }

    public function test_fetches_the_rate_from_the_provider_for_a_foreign_currency(): void
    {
        Http::fake([
            'v6.exchangerate-api.com/*' => Http::response([
                'result' => 'success',
                'base_code' => 'EUR',
                'target_code' => 'BRL',
                'conversion_rate' => 5.5,
            ]),
        ]);

        $rate = $this->service()->fetch(Currency::BRL);

        $this->assertSame(5.5, $rate->rate);
        $this->assertSame('exchangerate-api.com', $rate->source);
        $this->assertEqualsWithDelta(now()->timestamp, $rate->fetchedAt->timestamp, 5);
        // 110 BRL / 5.5 = 20.00 EUR
        $this->assertSame('20.00', $rate->convertToBase(110));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'pair/EUR/BRL'));
    }

    public function test_throws_when_the_provider_returns_an_error_status(): void
    {
        Http::fake(['v6.exchangerate-api.com/*' => Http::response('', 500)]);

        $this->expectException(ExchangeRateUnavailableException::class);

        $this->service()->fetch(Currency::BRL);
    }

    public function test_throws_when_the_provider_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->expectException(ExchangeRateUnavailableException::class);

        $this->service()->fetch(Currency::USD);
    }

    public function test_throws_when_the_payload_reports_an_error(): void
    {
        Http::fake([
            'v6.exchangerate-api.com/*' => Http::response(['result' => 'error', 'error-type' => 'unsupported-code']),
        ]);

        $this->expectException(ExchangeRateUnavailableException::class);

        $this->service()->fetch(Currency::USD);
    }
}
