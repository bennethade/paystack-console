<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds defensive headers and a Content Security Policy.
 *
 * Scripts are limited to this origin plus a per-request nonce (applied to the
 * Vite tags). 'unsafe-eval' is required by Alpine.js expression evaluation.
 * connect-src is 'self' only: the browser can never talk to api.paystack.co
 * directly, all calls go through the Laravel backend.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (config('app.csp_enabled', true) && ! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->policy($nonce));
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $scriptSrc = ["'self'", "'nonce-{$nonce}'", "'unsafe-eval'"];
        $styleSrc = ["'self'", "'unsafe-inline'", 'https://fonts.bunny.net'];
        $connectSrc = ["'self'"];
        $fontSrc = ["'self'", 'https://fonts.bunny.net', 'data:'];

        // Vite dev server (npm run dev) serves assets from another origin.
        $hotFile = public_path('hot');
        if (is_file($hotFile)) {
            $hot = rtrim(trim((string) file_get_contents($hotFile)), '/');
            $ws = preg_replace('#^http#', 'ws', $hot);
            array_push($scriptSrc, $hot);
            array_push($styleSrc, $hot);
            array_push($connectSrc, $hot, $ws);
            $fontSrc[] = $hot;
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scriptSrc),
            'style-src '.implode(' ', $styleSrc),
            'font-src '.implode(' ', $fontSrc),
            "img-src 'self' data:",
            'connect-src '.implode(' ', $connectSrc),
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
