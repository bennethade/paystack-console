<?php

namespace Tests\Feature;

use App\Models\ApiRequestLog;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExecuteEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaystack();
        $this->usePaystackKeys('NG');
    }

    public function test_initialize_transaction_end_to_end(): void
    {
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'message' => 'Authorization URL created',
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/0peioxfhpn',
                'access_code' => '0peioxfhpn',
                'reference' => '7PVGX8MEk85tgeEpVDtD',
            ],
        ])]);

        $response = $this->actingAs($this->developer())->execute('transaction.initialize', [
            'email' => 'customer@example.com',
            'amount' => 500000,
            'currency' => 'NGN',
            'metadata' => ['student_id' => 'STU-001'],
            'channels' => ['card', 'bank_transfer'],
        ]);

        $response->assertOk()
            ->assertJsonPath('response.status', 200)
            ->assertJsonPath('response.ok', true)
            ->assertJsonPath('response.reference', '7PVGX8MEk85tgeEpVDtD')
            ->assertJsonPath('response.links.0.label', 'Proceed to Payment')
            ->assertJsonPath('response.links.0.url', 'https://checkout.paystack.com/0peioxfhpn')
            ->assertJsonPath('request.method', 'POST')
            ->assertJsonPath('request.url', 'https://api.paystack.co/transaction/initialize')
            ->assertJsonPath('request.headers.Authorization', 'Bearer ****************')
            ->assertJsonPath('request.body.amount', 500000)
            ->assertJsonPath('context.country', 'NG');

        $this->assertStringContainsString('YOUR_SECRET_KEY', $response->json('curl'));
        $this->assertStringNotContainsString(self::TEST_SECRET, $response->getContent());

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer '.self::TEST_SECRET)
            && $r['metadata'] === ['student_id' => 'STU-001']
            && $r['channels'] === ['card', 'bank_transfer']);
    }

    public function test_paystack_errors_are_shown_with_documented_meaning(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => false,
            'message' => 'Invalid key',
            'meta' => ['nextStep' => 'Ensure that you provide the correct authorization key for the request'],
            'type' => 'validation_error',
            'code' => 'invalid_Key',
        ], 401)]);

        $this->actingAs($this->developer())->execute('balance.fetch')
            ->assertOk()
            ->assertJsonPath('response.status', 401)
            ->assertJsonPath('response.ok', false)
            ->assertJsonPath('response.paystack.message', 'Invalid key')
            ->assertJsonPath('response.paystack.next_step', 'Ensure that you provide the correct authorization key for the request')
            ->assertJsonPath('response.hint.source', 'Paystack documentation');
    }

    public function test_status_200_with_status_false_is_not_ok(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Transaction reference not found'])]);

        $this->actingAs($this->developer())->execute('transaction.verify', ['reference' => 'nope'])
            ->assertJsonPath('response.status', 200)
            ->assertJsonPath('response.ok', false);
    }

    public function test_get_request_path_and_query_are_built_correctly(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => []])]);

        $this->actingAs($this->developer())->execute('transaction.verify', ['reference' => 'ref/../with space'])->assertOk();
        $this->actingAs($this->developer())->execute('transaction.list', ['perPage' => 20, 'page' => 2, 'status' => 'success'])->assertOk();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.paystack.co/transaction/verify/ref%2F..%2Fwith%20space');
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://api.paystack.co/transaction?') && str_contains($r->url(), 'perPage=20&page=2&status=success'));
    }

    public function test_pagination_meta_is_normalised(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => true, 'data' => [['id' => 1]],
            'meta' => ['total' => 7316, 'skipped' => 0, 'perPage' => 50, 'page' => 1, 'pageCount' => 147],
        ])]);

        $this->actingAs($this->developer())->execute('transaction.list')
            ->assertJsonPath('response.pagination.type', 'page')
            ->assertJsonPath('response.pagination.page_count', 147)
            ->assertJsonPath('response.pagination.total', 7316);
    }

    public function test_endpoint_unavailable_in_market_is_refused_without_calling_paystack(): void
    {
        Http::fake();
        $user = $this->developer();
        $this->usePaystackKeys('KE');
        $this->actingAs($user)->putJson(route('explorer.context'), ['country' => 'KE', 'environment' => 'test']);

        $this->actingAs($user)->execute('dedicated_account.create', ['customer' => 'CUS_x'], ['country' => 'KE', 'currency' => 'KES'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'endpoint_unavailable');

        Http::assertNothingSent();
    }

    public function test_bank_list_is_unavailable_for_cote_divoire(): void
    {
        Http::fake();
        $user = $this->developer();
        $this->usePaystackKeys('CI');
        $this->actingAs($user)->putJson(route('explorer.context'), ['country' => 'CI', 'environment' => 'test']);

        $this->actingAs($user)->execute('bank.list', [], ['country' => 'CI', 'currency' => 'XOF'])->assertJsonPath('code', 'endpoint_unavailable');
        Http::assertNothingSent();
    }

    public function test_missing_credentials_are_reported_by_env_var_name(): void
    {
        Http::fake();
        config(['paystack.credentials.NG.test.secret' => null]);

        $this->actingAs($this->developer())->execute('balance.fetch')
            ->assertStatus(422)
            ->assertJsonPath('code', 'credentials_missing')
            ->assertSee('PAYSTACK_NG_TEST_SECRET_KEY');

        Http::assertNothingSent();
    }

    public function test_a_live_key_is_never_used_in_test_mode(): void
    {
        Http::fake();
        config(['paystack.credentials.NG.test.secret' => self::LIVE_SECRET]);

        $this->actingAs($this->developer())->execute('balance.fetch')->assertJsonPath('code', 'credentials_mismatch');
        Http::assertNothingSent();
    }

    public function test_public_key_endpoints_use_the_public_key(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true])]);
        $user = $this->developer();
        $this->usePaystackKeys('ZA');
        $this->actingAs($user)->putJson(route('explorer.context'), ['country' => 'ZA', 'environment' => 'test']);

        $this->actingAs($user)->execute('capitec_pay.requery', ['ref' => 'abc'], ['country' => 'ZA', 'currency' => 'ZAR'])->assertOk();

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer '.self::TEST_PUBLIC) && $r->method() === 'POST');
    }

    public function test_context_mismatch_from_a_stale_tab_is_rejected(): void
    {
        Http::fake();

        $this->actingAs($this->developer())->execute('balance.fetch', [], ['country' => 'GH', 'currency' => 'GHS'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'context_mismatch');

        Http::assertNothingSent();
    }

    public function test_live_requests_require_explicit_confirmation(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true])]);
        config(['paystack.live_enabled' => true]);
        app(Settings::class)->set('live_mode_enabled', true);
        $this->usePaystackKeys('NG', 'live');

        $user = User::factory()->liveEnabled()->create();
        $this->actingAs($user)->putJson(route('explorer.context'), ['country' => 'NG', 'environment' => 'live'])->assertOk();

        $live = ['environment' => 'live'];
        $this->actingAs($user)->execute('balance.fetch', [], $live)->assertForbidden()->assertJsonPath('code', 'live_mode_blocked');
        Http::assertNothingSent();

        $this->actingAs($user)->execute('balance.fetch', [], $live, confirmLive: true)->assertOk();
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer '.self::LIVE_SECRET));
        $this->assertDatabaseHas('audit_logs', ['action' => 'explorer.live_request', 'user_id' => $user->id]);
    }

    public function test_live_requests_are_blocked_if_live_mode_is_turned_off_mid_session(): void
    {
        Http::fake();
        config(['paystack.live_enabled' => true]);
        app(Settings::class)->set('live_mode_enabled', true);
        $this->usePaystackKeys('NG', 'live');
        $user = User::factory()->liveEnabled()->create();
        $this->actingAs($user)->putJson(route('explorer.context'), ['country' => 'NG', 'environment' => 'live']);

        app(Settings::class)->set('live_mode_enabled', false);

        // The resolver falls back to test, so a page still claiming "live" is stale.
        $this->actingAs($user)->execute('balance.fetch', [], ['environment' => 'live'], confirmLive: true)->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_mock_mode_never_calls_paystack(): void
    {
        Http::fake();
        config(['paystack.mock_mode' => true]);

        $this->actingAs($this->developer())->execute('transaction.initialize', ['email' => 'a@b.co', 'amount' => 5000])
            ->assertOk()
            ->assertJsonPath('mock', true)
            ->assertJsonPath('response.is_mock', true)
            ->assertJsonPath('response.links.0.label', 'Proceed to Payment');

        Http::assertNothingSent();
        $this->assertTrue(ApiRequestLog::first()->is_mock);
    }

    public function test_execution_is_rate_limited_per_user(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true])]);
        config(['paystack.rate_limits.execute_per_minute' => 2]);
        $user = $this->developer();

        $this->actingAs($user)->execute('balance.fetch')->assertOk();
        $this->actingAs($user)->execute('balance.fetch')->assertOk();
        $this->actingAs($user)->execute('balance.fetch')->assertTooManyRequests();
    }

    public function test_untrusted_redirect_urls_are_not_offered_as_actions(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => [
            'authorization_url' => 'https://evil.example.com/phish', 'reference' => 'r1',
        ]])]);

        $this->actingAs($this->developer())->execute('transaction.initialize', ['email' => 'a@b.co', 'amount' => 5000])
            ->assertJsonPath('response.links', []);
    }

    public function test_payment_callback_verifies_the_transaction_in_test_mode(): void
    {
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'message' => 'Verification successful', 'data' => [
            'status' => 'success', 'reference' => 'ref_1', 'amount' => 500000, 'currency' => 'NGN', 'gateway_response' => 'Successful', 'channel' => 'card',
        ]])]);

        $this->actingAs($this->developer())->get(route('explorer.callback', ['reference' => 'ref_1', 'trxref' => 'ref_1']))
            ->assertOk()
            ->assertSee('success')
            ->assertSee('₦5,000.00');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.paystack.co/transaction/verify/ref_1');
    }
}
