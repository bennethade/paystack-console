<?php

namespace Tests\Feature;

use App\Models\ApiRequestLog;
use App\Models\Endpoint;
use App\Models\SavedRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HistoryReplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaystack();
        $this->usePaystackKeys('NG');
    }

    private function runOnce($user, string $key = 'charge.submit_pin', array $params = ['pin' => '1234', 'reference' => 'ref_1']): ApiRequestLog
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true])]);
        $this->actingAs($user)->execute($key, $params)->assertOk();

        return ApiRequestLog::latest('id')->firstOrFail();
    }

    public function test_history_lists_only_the_users_own_requests(): void
    {
        $me = $this->developer();
        $other = $this->developer();
        $this->runOnce($me, 'balance.fetch', []);
        $this->runOnce($other, 'transaction.totals', []);

        $response = $this->actingAs($me)->get(route('explorer.history.index'))->assertOk();

        $this->assertSame(['balance.fetch'], $response->viewData('logs')->pluck('endpoint_key')->all());
    }

    public function test_replay_loads_the_form_without_executing(): void
    {
        $user = $this->developer();
        $log = $this->runOnce($user);
        Http::fake();

        $redirect = $this->actingAs($user)->get(route('explorer.history.replay', $log));
        $redirect->assertRedirect();
        $this->assertStringContainsString('replay='.$log->uuid, $redirect->headers->get('Location'));

        $page = $this->actingAs($user)->get('/api-explorer/charges/submit-pin?replay='.$log->uuid)->assertOk();
        $page->assertSee('Replaying request from');
        $page->assertSee('re-entered');

        preg_match('#id="endpoint-prefill">(.*?)</script>#s', $page->getContent(), $m);
        $prefill = json_decode($m[1], true);
        $this->assertSame(['reference' => 'ref_1'], $prefill['params'], 'masked PIN is not pre-filled');
        $this->assertSame(['pin'], $prefill['masked']);

        Http::assertNothingSent();
    }

    public function test_duplicate_creates_a_saved_configuration(): void
    {
        $user = $this->developer();
        $log = $this->runOnce($user, 'transaction.verify', ['reference' => 'abc']);

        $this->actingAs($user)->post(route('explorer.history.duplicate', $log))->assertRedirect();

        $saved = SavedRequest::firstOrFail();
        $this->assertSame($user->id, $saved->user_id);
        $this->assertSame(['reference' => 'abc'], $saved->params);

        $this->actingAs($user)->get('/api-explorer/transactions/verify?saved='.$saved->id)->assertSee('Loaded saved configuration');
    }

    public function test_saved_configurations_can_be_stored_without_secrets(): void
    {
        $user = $this->developer();

        $this->actingAs($user)->postJson(route('explorer.saved.store', 'charge.submit_otp'), [
            'name' => 'OTP step', 'params' => ['otp' => '123456', 'reference' => 'r1', 'not_a_param' => 'x'],
        ])->assertCreated();

        $saved = SavedRequest::firstOrFail();
        $this->assertSame(['otp' => '********', 'reference' => 'r1'], $saved->params);
    }

    public function test_other_users_saved_configurations_are_not_loaded(): void
    {
        $owner = $this->developer();
        $saved = SavedRequest::create([
            'user_id' => $owner->id,
            'endpoint_id' => Endpoint::where('key', 'transaction.verify')->value('id'),
            'name' => 'mine', 'params' => ['reference' => 'secret-ref'],
        ]);

        $this->actingAs($this->developer())->get('/api-explorer/transactions/verify?saved='.$saved->id)->assertDontSee('secret-ref');
    }

    public function test_entries_can_be_deleted_and_history_cleared(): void
    {
        $user = $this->developer();
        $first = $this->runOnce($user, 'balance.fetch', []);
        $this->runOnce($user, 'balance.fetch', []);
        $mine = ApiRequestLog::count();
        $other = $this->runOnce($this->developer(), 'balance.fetch', []);

        $this->actingAs($user)->delete(route('explorer.history.destroy', $first))->assertRedirect();
        $this->assertSame($mine - 1 + 1, ApiRequestLog::count());

        $this->actingAs($user)->delete(route('explorer.history.clear'))->assertRedirect();
        $this->assertSame([$other->id], ApiRequestLog::pluck('id')->all());
    }

    public function test_history_detail_shows_a_curl_with_placeholder(): void
    {
        $user = $this->developer();
        $log = $this->runOnce($user, 'transaction.verify', ['reference' => 'abc']);

        $this->actingAs($user)->get(route('explorer.history.show', $log))
            ->assertOk()
            ->assertSee('YOUR_SECRET_KEY')
            ->assertSee('https://api.paystack.co/transaction/verify/abc');
    }
}
