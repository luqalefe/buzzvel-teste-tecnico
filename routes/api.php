<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentRequestController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

// ── Public (guest) endpoints ────────────────────────────────────────────────
// Strict per-email+IP throttle to blunt brute-force / credential stuffing.
Route::middleware('throttle:login')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
});

// ── Authenticated endpoints ─────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/user', [AuthController::class, 'me'])->name('user');

    // ── Payment requests ────────────────────────────────────────────────
    Route::get('/payment-requests', [PaymentRequestController::class, 'index'])
        ->name('payment-requests.index');
    // Must precede the /{paymentRequest} route so these aren't read as ids.
    Route::get('/payment-requests/stats', [PaymentRequestController::class, 'stats'])
        ->name('payment-requests.stats');
    Route::get('/payment-requests/preview', [PaymentRequestController::class, 'preview'])
        ->name('payment-requests.preview');
    Route::post('/payment-requests', [PaymentRequestController::class, 'store'])
        ->middleware(EnsureIdempotency::class)
        ->name('payment-requests.store');
    Route::get('/payment-requests/{paymentRequest}', [PaymentRequestController::class, 'show'])
        ->name('payment-requests.show');
    Route::patch('/payment-requests/{paymentRequest}', [PaymentRequestController::class, 'update'])
        ->middleware(EnsureIdempotency::class)
        ->name('payment-requests.update');
});
