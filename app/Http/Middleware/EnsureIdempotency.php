<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opt-in idempotency for unsafe (POST) endpoints, à la Stripe/Adyen.
 *
 * When a client sends an `Idempotency-Key` header, the first response is stored
 * and replayed on any retry with the same key + payload — so a network retry or
 * double-submit never creates a duplicate payment request (and never fires a
 * second exchange-rate call). A same key with a different payload is a 409.
 * Server errors (5xx, e.g. provider unavailable) are NOT stored, so they stay
 * retryable.
 */
class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        $user = $request->user();

        // Opt-in: only applies when a key is supplied for an authenticated user.
        if (! $key || ! $user) {
            return $next($request);
        }

        if (mb_strlen($key) > 255) {
            return response()->json(['message' => 'The Idempotency-Key is too long.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $hash = hash('sha256', $request->getContent());

        $existing = IdempotencyKey::query()
            ->where('user_id', $user->id)
            ->where('key', $key)
            ->first();

        if ($existing) {
            if ($existing->request_hash !== $hash) {
                return response()->json([
                    'message' => 'This Idempotency-Key was already used with a different request.',
                ], Response::HTTP_CONFLICT);
            }

            return response($existing->response_body, $existing->response_status)
                ->header('Content-Type', 'application/json')
                ->header('Idempotent-Replayed', 'true');
        }

        $response = $next($request);

        // Persist only final outcomes; leave transient 5xx (e.g. the rate
        // provider being down) un-stored so the key can be retried.
        if ($response->getStatusCode() < 500) {
            IdempotencyKey::create([
                'user_id' => $user->id,
                'key' => $key,
                'request_hash' => $hash,
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ]);
        }

        return $response;
    }
}
