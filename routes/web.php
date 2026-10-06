<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Explorer;
use App\Http\Controllers\WebhookReceiverController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication (custom, no starter kit)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');

    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:auth-forms')->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:auth-forms')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:auth-forms')->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Paystack webhook receiver (public, CSRF-exempt, rate limited)
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/paystack/{environment}/{country}', WebhookReceiverController::class)
    ->where(['environment' => 'test|live', 'country' => '[A-Za-z]{2}'])
    ->middleware('throttle:webhooks')
    ->name('webhooks.paystack');

/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active', 'throttle:app'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::redirect('/', '/api-explorer');

    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::delete('/account/sessions', [AccountController::class, 'destroyOtherSessions'])->name('account.sessions.destroy');

    Route::prefix('api-explorer')->name('explorer.')->middleware('can:explorer.view')->group(function () {
        Route::get('/', [Explorer\ExplorerController::class, 'index'])->name('index');
        Route::put('/context', [Explorer\ContextController::class, 'update'])->name('context');
        Route::get('/search', Explorer\SearchController::class)->name('search');
        Route::get('/callback', Explorer\PaymentCallbackController::class)->name('callback');

        Route::post('/execute/{endpoint}', Explorer\ExecuteController::class)
            ->middleware(['can:explorer.execute', 'throttle:paystack-execute'])
            ->name('execute');

        Route::post('/favorites/{endpoint}', [Explorer\FavoriteController::class, 'toggle'])->name('favorites.toggle');

        Route::get('/history', [Explorer\HistoryController::class, 'index'])->name('history.index');
        Route::delete('/history', [Explorer\HistoryController::class, 'clear'])->name('history.clear');
        Route::get('/history/{log}', [Explorer\HistoryController::class, 'show'])->name('history.show');
        Route::get('/history/{log}/replay', [Explorer\HistoryController::class, 'replay'])->name('history.replay');
        Route::post('/history/{log}/duplicate', [Explorer\HistoryController::class, 'duplicate'])->name('history.duplicate');
        Route::delete('/history/{log}', [Explorer\HistoryController::class, 'destroy'])->name('history.destroy');

        Route::get('/saved', [Explorer\SavedRequestController::class, 'index'])->name('saved.index');
        Route::post('/saved/{endpoint}', [Explorer\SavedRequestController::class, 'store'])->name('saved.store');
        Route::delete('/saved/{saved}', [Explorer\SavedRequestController::class, 'destroy'])->name('saved.destroy');

        Route::middleware('can:webhooks.view')->group(function () {
            Route::get('/webhooks', [Explorer\WebhookController::class, 'index'])->name('webhooks.index');
            Route::post('/webhooks/verify-signature', [Explorer\WebhookController::class, 'verifySignature'])->name('webhooks.verify');
            Route::get('/webhooks/{event}', [Explorer\WebhookController::class, 'show'])->name('webhooks.show');
            Route::post('/webhooks/{event}/reverify', [Explorer\WebhookController::class, 'reverify'])->name('webhooks.reverify');
            Route::delete('/webhooks/{event}', [Explorer\WebhookController::class, 'destroy'])->name('webhooks.destroy');
        });

        // Must stay last: /api-explorer/{category}/{endpoint}, e.g. /api-explorer/transactions/initialize
        Route::get('/{category}/{endpoint}', [Explorer\ExplorerController::class, 'show'])
            ->where(['category' => '[a-z0-9\-]+', 'endpoint' => '[a-z0-9\-]+'])
            ->name('endpoint');
    });

    /*
    | Admin (custom; no Filament/Nova)
    */
    Route::prefix('admin')->name('admin.')->middleware('can:admin.access')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::resource('countries', Admin\CountryController::class)->except('show');
        Route::resource('currencies', Admin\CurrencyController::class)->except('show');
        Route::resource('categories', Admin\CategoryController::class)->except(['show', 'destroy']);

        Route::post('endpoints/import', [Admin\EndpointController::class, 'import'])->name('endpoints.import');
        Route::post('endpoints/{endpoint}/toggle', [Admin\EndpointController::class, 'toggle'])->name('endpoints.toggle');
        Route::resource('endpoints', Admin\EndpointController::class)->except('show');

        Route::resource('users', Admin\UserController::class)->except(['show', 'destroy']);

        Route::get('logs', [Admin\RequestLogController::class, 'index'])->name('logs.index');
        Route::delete('logs', [Admin\RequestLogController::class, 'purge'])->name('logs.purge');

        Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::delete('webhooks', [Admin\SettingsController::class, 'purgeWebhooks'])->name('webhooks.purge');

        Route::get('audit', Admin\AuditLogController::class)->name('audit.index');
    });
});
