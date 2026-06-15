<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Per-request nonce, shared with the Blade shell so its inline boot
        // script can be allow-listed by the CSP (used in non-local envs).
        $nonce = base64_encode(random_bytes(16));
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce, $request));

        return $response;
    }

    private function contentSecurityPolicy(string $nonce, Request $request): string
    {
        $local = app()->environment('local');

        // The Swagger UI (l5-swagger) boots from an inline <script> we neither
        // own nor can nonce, so it requires 'unsafe-inline'. Scope that
        // relaxation strictly to the documentation routes — the SPA and the API
        // keep the locked-down, nonce-only policy below.
        $swaggerUi = $request->is('api/documentation', 'api/oauth2-callback', 'docs', 'docs/*');

        // Local dev needs inline/eval + the Vite HMR websocket; production locks
        // scripts down to same-origin + the nonce'd boot script.
        $scriptSrc = ($local || $swaggerUi)
            ? "script-src 'self' 'unsafe-inline' 'unsafe-eval'"
            : "script-src 'self' 'nonce-{$nonce}'";

        $connectSrc = $local
            ? "connect-src 'self' ws: http://localhost:* http://127.0.0.1:*"
            : "connect-src 'self'";

        return implode('; ', [
            "default-src 'self'",
            $scriptSrc,
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net data:",
            'img-src \'self\' data:',
            $connectSrc,
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
        ]);
    }
}
