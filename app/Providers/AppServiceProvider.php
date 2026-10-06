<?php

namespace App\Providers;

use App\Enums\Role;
use App\Http\View\ExplorerComposer;
use App\Models\User;
use App\Services\Paystack\EndpointRegistry;
use App\Services\Paystack\PaystackCountryResolver;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(EndpointRegistry::class);
        $this->app->singleton(PaystackCountryResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerGates();
        $this->registerRateLimiters();

        Password::defaults(function () {
            $rule = Password::min(10)->letters()->mixedCase()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        View::composer('layouts.app', ExplorerComposer::class);
    }

    private function registerGates(): void
    {
        foreach (Role::PERMISSIONS as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
    }

    private function registerRateLimiters(): void
    {
        // Explorer execution: per user and per IP so the app cannot be used
        // as an open proxy to Paystack.
        RateLimiter::for('paystack-execute', fn (Request $request) => [
            Limit::perMinute((int) config('paystack.rate_limits.execute_per_minute'))->by('user:'.$request->user()?->id),
            Limit::perMinute((int) config('paystack.rate_limits.execute_per_ip_per_minute'))->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute((int) config('paystack.rate_limits.webhooks_per_minute'))->by($request->ip()));

        RateLimiter::for('auth-forms', fn (Request $request) => Limit::perMinute(5)->by(
            strtolower((string) $request->input('email')).'|'.$request->ip()
        ));

        RateLimiter::for('app', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
