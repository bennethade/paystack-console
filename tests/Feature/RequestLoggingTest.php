<?php

namespace Tests\Feature;

use App\Models\ApiRequestLog;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaystack();
        $this->usePaystackKeys('NG');
    }

    public function test_history_entry_records_the_request_safely(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'message' => 'Charge attempted', 'data' => ['status' => 'send_otp']])]);
        $user = $this->developer();

        $response = $this->actingAs($user)->execute('charge.submit_pin', ['pin' => '1234', 'reference' => 'ref_9']);
        $response->assertOk();

        $log = ApiRequestLog::firstOrFail();
        $this->assertSame($response->json('history_id'), $log->uuid);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('charge.submit_pin', $log->endpoint_key);
        $this->assertSame('NG', $log->country_code);
        $this->assertSame('NGN', $log->currency_code);
        $this->assertSame('test', $log->environment);
        $this->assertSame(200, $log->status_code);
        $this->assertNotNull($log->duration_ms);

        $this->assertSame('********', $log->params['pin'], 'PIN must never be stored');
        $this->assertSame('********', $log->request_body['pin']);
        $this->assertSame('ref_9', $log->params['reference']);
        $this->assertSame('Bearer ****************', $log->request_headers['Authorization']);
        $this->assertSame('Charge attempted', $log->decodedResponse()['message']);

        // And the inspector shown to the user is masked too.
        $response->assertJsonPath('request.body.pin', '********');
    }

    public function test_secret_key_never_reaches_the_database_or_the_log(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => ['echo' => self::TEST_SECRET]])]);
        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event->message.json_encode($event->context);
        });

        $this->actingAs($this->developer())->execute('balance.fetch')->assertOk();

        foreach (['api_request_logs', 'audit_logs'] as $table) {
            foreach (DB::table($table)->get() as $row) {
                $this->assertStringNotContainsString(self::TEST_SECRET, json_encode($row), "{$table} contains the key");
            }
        }
        $this->assertStringNotContainsString(self::TEST_SECRET, implode("\n", $logged));
        $this->assertNotEmpty(array_filter($logged, fn ($l) => str_contains($l, 'Paystack API request')));
    }

    public function test_card_numbers_in_requests_are_masked(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true])]);

        $this->actingAs($this->developer())->execute('charge.create', [
            'email' => 'a@b.co', 'amount' => 5000,
            'metadata' => ['card' => ['number' => '4084084084084081', 'cvv' => '408']],
        ])->assertOk();

        $stored = json_encode(ApiRequestLog::firstOrFail()->request_body);
        $this->assertStringNotContainsString('4084084084084081', $stored);
        $this->assertStringContainsString('408408******4081', $stored);
        $this->assertStringNotContainsString('"408"', $stored);
    }

    public function test_history_can_be_disabled(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true])]);
        app(Settings::class)->set('history_enabled', false);

        $this->actingAs($this->developer())->execute('balance.fetch')->assertOk()->assertJsonPath('history_id', null);
        $this->assertSame(0, ApiRequestLog::count());
    }

    public function test_large_responses_are_truncated(): void
    {
        config(['paystack.history.max_response_kb' => 1]);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => str_repeat('x', 5000)])]);

        $this->actingAs($this->developer())->execute('balance.fetch')->assertOk();

        $log = ApiRequestLog::firstOrFail();
        $this->assertTrue($log->response_truncated);
        $this->assertLessThanOrEqual(1024, strlen($log->response_body));
    }

    public function test_prune_command_respects_retention(): void
    {
        ApiRequestLog::create(['endpoint_key' => 'old', 'environment' => 'test', 'method' => 'GET', 'path' => '/x', 'url' => 'x'])
            ->forceFill(['created_at' => now()->subDays(40)])->save();
        ApiRequestLog::create(['endpoint_key' => 'new', 'environment' => 'test', 'method' => 'GET', 'path' => '/x', 'url' => 'x']);

        $this->artisan('paystack:prune-history', ['--days' => 30])->assertSuccessful();

        $this->assertSame(['new'], ApiRequestLog::pluck('endpoint_key')->all());
    }
}
