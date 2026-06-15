<?php

use App\Exceptions\ExchangeRateUnavailableException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apply the 'api' rate limiter (60/min) to all API routes.
        $middleware->throttleApi();

        // Security response headers on every response.
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // These are expected, fully-handled domain conditions — don't log them
        // (the provider exception also carries the API key in its URL).
        $exceptions->dontReport([
            ExchangeRateUnavailableException::class,
            InvalidStatusTransitionException::class,
        ]);

        // External exchange-rate provider is unreachable → 503 Service Unavailable.
        $exceptions->render(function (ExchangeRateUnavailableException $e): JsonResponse {
            return response()->json([
                'message' => 'The exchange-rate provider is currently unavailable. Please try again later.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });

        // Illegal status change (e.g. approving an already-rejected request) → 422.
        $exceptions->render(function (InvalidStatusTransitionException $e): JsonResponse {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });
    })->create();
