<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaystack();
        foreach (['NG', 'GH', 'KE', 'ZA', 'CI'] as $country) {
            $this->usePaystackKeys($country);
            $this->usePaystackKeys($country, 'live');
        }
    }

    public function test_no_page_ever_contains_a_paystack_key(): void
    {
        $admin = User::factory()->admin()->liveEnabled()->create();
        $pages = [
            '/api-explorer', '/api-explorer/transactions/initialize', '/api-explorer/banks/list', '/api-explorer/capitec-pay/requery',
            '/api-explorer/webhooks', '/api-explorer/history', '/account',
            '/admin', '/admin/countries', '/admin/settings', '/admin/endpoints', '/admin/countries/1/edit',
        ];

        foreach ($pages as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            foreach ([self::TEST_SECRET, self::LIVE_SECRET, self::TEST_PUBLIC] as $key) {
                $this->assertStringNotContainsString($key, $html, "{$url} leaks a key");
            }
        }
    }

    public function test_env_file_is_not_served(): void
    {
        $this->get('/.env')->assertNotFound();
    }

    public function test_security_headers_are_present(): void
    {
        $response = $this->actingAs($this->developer())->get('/api-explorer');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("connect-src 'self'", $csp, 'the browser may only talk to this app, never to Paystack');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_csrf_protection_applies_to_execution(): void
    {
        $this->withMiddleware(PreventRequestForgery::class);
        $this->app->instance('env', 'local'); // CSRF middleware skips the "testing" environment

        $user = $this->developer();
        $this->actingAs($user)->postJson(route('explorer.execute', 'balance.fetch'), [
            'context' => ['country' => 'NG', 'currency' => 'NGN', 'environment' => 'test'],
        ])->assertStatus(419);
    }

    public function test_session_cookie_is_http_only(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_passwords_are_hashed(): void
    {
        $user = User::factory()->create(['password' => 'PlainText123']);

        $this->assertNotSame('PlainText123', $user->getAttributes()['password']);
        $this->assertTrue(password_verify('PlainText123', $user->getAttributes()['password']));
    }

    public function test_reflected_input_is_escaped(): void
    {
        $this->actingAs($this->developer())
            ->get('/api-explorer/history?endpoint='.urlencode('<script>alert(1)</script>'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_explorer_cannot_be_used_to_reach_arbitrary_hosts(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true])]);

        // Path parameters are URL-encoded, so they cannot break out of the Paystack path.
        $this->actingAs($this->developer())->execute('transaction.verify', ['reference' => '../../../evil.com/x'])->assertOk();

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.paystack.co/transaction/verify/..%2F'));
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'https://api.paystack.co/'));
    }

    public function test_json_responses_never_echo_credentials(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true])]);

        $content = $this->actingAs($this->developer())->execute('balance.fetch')->assertOk()->getContent();

        $this->assertStringNotContainsString(self::TEST_SECRET, $content);
        $this->assertStringContainsString('Bearer ****************', $content);
    }
}
