<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sweep pending payment requests past their 48h TTL into the expired state (US-C1).
Schedule::command('payment-requests:expire')->hourly();
