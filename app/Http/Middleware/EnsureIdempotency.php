<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opt-in idempotency for unsafe (POST/PATCH) endpoints, à la Stripe/Adyen.
 *
 * When a client sends an `Idempotency-Key` header, the first response is stored
 * and replayed on any retry with the same key + request — so a network retry or
 * double-submit never acts twice (no duplicate create, no second exchange-rate
 * call, no double approval). The fingerprint covers method + path + body, so the
 * same key reused for a different request (or a different resource) is a 409.
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

        // Fingerprint the whole request (method + path + body), not just the
        // body, so the same key on a different resource/route can't wrongly replay.
        $hash = hash('sha256', implode('|', [$request->method(), $request->path(), $request->getContent()]));

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
