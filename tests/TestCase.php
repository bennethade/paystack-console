<?php

namespace Tests;

use App\Models\User;
use App\Services\Paystack\OpenApi\EndpointImporter;
use App\Services\Paystack\PaystackCountryResolver;
use App\Support\Settings;
use Database\Seeders\PaystackReferenceSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    public const TEST_SECRET = 'sk_test_unit0000000000000000000000000000000';

    public const TEST_PUBLIC = 'pk_test_unit0000000000000000000000000000000';

    public const LIVE_SECRET = 'sk_live_unit0000000000000000000000000000000';

    /** Parsed registry, shared across tests (parsing the spec is the slow part). */
    private static ?array $builtRegistry = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Automated tests must never reach the real Paystack API.
        Http::preventStrayRequests();

        // Deterministic credentials, independent of the developer's .env.
        foreach (array_keys(config('paystack.countries')) as $code) {
            config([
                "paystack.credentials.{$code}.test" => ['secret' => null, 'public' => null],
                "paystack.credentials.{$code}.live" => ['secret' => null, 'public' => null],
            ]);
        }
        config([
            'paystack.live_enabled' => false,
            'paystack.mock_mode' => false,
            'paystack.webhooks.enabled' => true,
            'paystack.webhooks.enforce_ip_allowlist' => false,
        ]);
    }

    /**
     * Seed countries, currencies and the full endpoint registry.
     */
    protected function seedPaystack(): void
    {
        $this->seed(PaystackReferenceSeeder::class);

        $importer = app(EndpointImporter::class);
        self::$builtRegistry ??= $importer->build(config('paystack.openapi.spec_path'));
        $importer->importBuilt(self::$builtRegistry);

        app(PaystackCountryResolver::class)->flush();
        app(Settings::class)->flush();
    }

    protected function usePaystackKeys(string $country = 'NG', string $env = 'test', ?string $secret = null, ?string $public = null): void
    {
        config(["paystack.credentials.{$country}.{$env}" => [
            'secret' => $secret ?? ($env === 'live' ? self::LIVE_SECRET : self::TEST_SECRET),
            'public' => $public ?? self::TEST_PUBLIC,
        ]]);
    }

    protected function developer(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function admin(array $attributes = []): User
    {
        return User::factory()->admin()->create($attributes);
    }

    /**
     * POST to the execute endpoint with the context the page would send.
     */
    protected function execute(string $key, array $params = [], array $context = [], bool $confirmLive = false)
    {
        return $this->postJson(route('explorer.execute', $key), [
            'params' => $params,
            'context' => $context + ['country' => 'NG', 'currency' => 'NGN', 'environment' => 'test'],
            'confirm_live' => $confirmLive,
        ]);
    }
}
