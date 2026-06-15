<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test should ever hit the real network: any unfaked outgoing HTTP
        // request fails loudly instead of silently returning an empty 200.
        Http::preventStrayRequests();
    }
}
