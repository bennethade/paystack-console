<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class]);

        $middleware->alias(['active' => EnsureUserIsActive::class]);

        // Webhook signatures are computed over exact bytes; never trim them.
        $middleware->trimStrings(except: ['payload', 'signature']);

        // Paystack posts webhooks without a CSRF token; they are verified by
        // HMAC signature instead.
        $middleware->preventRequestForgery(except: ['webhooks/paystack/*']);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('explorer.index'));

        // Behind a load balancer / tunnel, set TRUSTED_PROXIES so request IPs
        // (rate limiting, webhook IP checks, audit log) are correct.
        $proxies = env('TRUSTED_PROXIES');
        if ($proxies) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Never let request bodies (which may hold PINs / OTPs) reach logs.
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'params']);
    })->create();
