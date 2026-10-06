<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $payload = '{"event":"charge.success","data":{"id":302961,"reference":"qTPrJoy9Bx","amount":10000,"status":"success"}}';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaystack();
        $this->usePaystackKeys('NG');
    }

    private function sendWebhook(string $signature, string $country = 'ng', string $env = 'test', array $server = [])
    {
        return $this->call('POST', "/webhooks/paystack/{$env}/{$country}", [], [], [], array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        ], $server), $this->payload);
    }

    public function test_valid_signature_is_accepted_and_stored_without_csrf(): void
    {
        $signature = hash_hmac('sha512', $this->payload, self::TEST_SECRET);

        $this->sendWebhook($signature, server: ['REMOTE_ADDR' => '52.31.139.75'])->assertOk()->assertJson(['status' => 'ok']);

        $event = WebhookEvent::firstOrFail();
        $this->assertTrue($event->signature_valid);
        $this->assertTrue($event->ip_allowed);
        $this->assertSame('charge.success', $event->event);
        $this->assertSame('qTPrJoy9Bx', $event->reference);
        $this->assertSame('NG', $event->country_code);
        $this->assertSame($this->payload, $event->payload);
    }

    public function test_invalid_signature_is_rejected_but_recorded(): void
    {
        $this->sendWebhook(hash_hmac('sha512', $this->payload, 'sk_test_someone_else'))->assertStatus(401);

        $event = WebhookEvent::firstOrFail();
        $this->assertFalse($event->signature_valid);
        $this->assertStringContainsString('does not match', $event->verification_note);
    }

    public function test_tampered_payload_fails_verification(): void
    {
        $signature = hash_hmac('sha512', $this->payload, self::TEST_SECRET);
        $this->payload = str_replace('10000', '99999999', $this->payload);

        $this->sendWebhook($signature)->assertStatus(401);
    }

    public function test_unknown_market_or_environment_returns_404(): void
    {
        $this->sendWebhook('x', 'zz')->assertNotFound();
        $this->sendWebhook('x', 'ng', 'staging')->assertNotFound();
        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_ip_allowlist_can_be_enforced(): void
    {
        config(['paystack.webhooks.enforce_ip_allowlist' => true]);
        $signature = hash_hmac('sha512', $this->payload, self::TEST_SECRET);

        $this->sendWebhook($signature, server: ['REMOTE_ADDR' => '10.0.0.5'])->assertForbidden();
        $this->sendWebhook($signature, server: ['REMOTE_ADDR' => '52.49.173.169'])->assertOk();
        $this->assertSame(1, WebhookEvent::count());
    }

    public function test_webhooks_can_be_disabled(): void
    {
        app(Settings::class)->set('webhooks_enabled', false);

        $this->sendWebhook('x')->assertNotFound();
    }

    public function test_events_are_listed_and_viewable(): void
    {
        $this->sendWebhook(hash_hmac('sha512', $this->payload, self::TEST_SECRET));
        $event = WebhookEvent::firstOrFail();
        $user = $this->developer();

        $this->actingAs($user)->get(route('explorer.webhooks.index'))->assertOk()->assertSee('charge.success')->assertSee('/webhooks/paystack/test/ng');
        $this->actingAs($user)->get(route('explorer.webhooks.show', $event))->assertOk()->assertSee('qTPrJoy9Bx');
    }

    public function test_events_can_be_reverified_after_a_key_is_configured(): void
    {
        config(['paystack.credentials.NG.test.secret' => null]);
        $signature = hash_hmac('sha512', $this->payload, self::TEST_SECRET);
        $this->sendWebhook($signature)->assertStatus(401);
        $event = WebhookEvent::firstOrFail();
        $this->assertStringContainsString('cannot verify', $event->verification_note);

        $this->usePaystackKeys('NG');
        $this->actingAs($this->developer())->post(route('explorer.webhooks.reverify', $event))->assertRedirect();
        $this->assertTrue($event->fresh()->signature_valid);
    }

    public function test_signature_checker_tool(): void
    {
        $user = $this->developer();
        $signature = hash_hmac('sha512', $this->payload, self::TEST_SECRET);

        $this->actingAs($user)->postJson(route('explorer.webhooks.verify'), [
            'payload' => $this->payload, 'signature' => $signature, 'country' => 'NG', 'environment' => 'test',
        ])->assertOk()->assertJson(['valid' => true])->assertDontSee(self::TEST_SECRET);

        $this->actingAs($user)->postJson(route('explorer.webhooks.verify'), [
            'payload' => $this->payload.' ', 'signature' => $signature, 'country' => 'NG', 'environment' => 'test',
        ])->assertJson(['valid' => false]);
    }

    public function test_only_admins_can_delete_events(): void
    {
        $this->sendWebhook('bad');
        $event = WebhookEvent::firstOrFail();

        $this->actingAs($this->developer())->delete(route('explorer.webhooks.destroy', $event))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->delete(route('explorer.webhooks.destroy', $event))->assertRedirect();
        $this->assertSame(0, WebhookEvent::count());
    }
}
