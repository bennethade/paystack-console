<?php

namespace Tests\Feature;

use App\Models\PaystackCountry;
use App\Models\User;
use App\Models\UserApiPreference;
use App\Services\Paystack\PaystackCountryResolver;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Country / currency / environment selection.
 */
class ContextSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaystack();
    }

    public function test_seeded_markets_match_documented_currencies(): void
    {
        $expected = ['NG' => ['NGN', 'USD'], 'GH' => ['GHS'], 'KE' => ['KES', 'USD'], 'ZA' => ['ZAR'], 'CI' => ['XOF']];

        foreach ($expected as $code => $currencies) {
            $country = PaystackCountry::where('code', $code)->firstOrFail();
            $this->assertEqualsCanonicalizing($currencies, $country->currencies->pluck('code')->all(), $code);
        }
    }

    public function test_default_context_is_nigeria_test(): void
    {
        $this->actingAs($this->developer())->get('/api-explorer')
            ->assertOk()
            ->assertSee('"country":"NG"', false)
            ->assertSee('"environment":"test"', false);
    }

    public function test_user_can_switch_country_and_currency(): void
    {
        $user = $this->developer();

        $this->actingAs($user)
            ->putJson(route('explorer.context'), ['country' => 'KE', 'currency' => 'KES', 'environment' => 'test'])
            ->assertOk()
            ->assertJsonPath('context.country', 'KE')
            ->assertJsonPath('context.currency', 'KES');

        $this->assertDatabaseHas('user_api_preferences', ['user_id' => $user->id, 'country_code' => 'KE', 'currency_code' => 'KES']);
    }

    public function test_currency_must_belong_to_the_country(): void
    {
        $this->actingAs($this->developer())
            ->putJson(route('explorer.context'), ['country' => 'GH', 'currency' => 'NGN', 'environment' => 'test'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency' => 'Currency NGN is invalid for the selected country (Ghana).']);
    }

    public function test_switching_country_without_currency_uses_its_default(): void
    {
        $this->actingAs($this->developer())
            ->putJson(route('explorer.context'), ['country' => 'ZA', 'environment' => 'test'])
            ->assertJsonPath('context.currency', 'ZAR');
    }

    public function test_unknown_or_disabled_country_is_rejected(): void
    {
        PaystackCountry::where('code', 'CI')->update(['is_enabled' => false]);
        app(PaystackCountryResolver::class)->flush();

        $user = $this->developer();
        $this->actingAs($user)->putJson(route('explorer.context'), ['country' => 'CI', 'environment' => 'test'])->assertUnprocessable();
        $this->actingAs($user)->putJson(route('explorer.context'), ['country' => 'US', 'environment' => 'test'])->assertUnprocessable();
    }

    public function test_live_mode_is_refused_when_disabled(): void
    {
        $user = User::factory()->liveEnabled()->create();

        $this->actingAs($user)
            ->putJson(route('explorer.context'), ['country' => 'NG', 'currency' => 'NGN', 'environment' => 'live'])
            ->assertJsonValidationErrors('environment');
    }

    public function test_live_mode_requires_env_flag_setting_and_user_permission(): void
    {
        config(['paystack.live_enabled' => true]);
        app(Settings::class)->set('live_mode_enabled', true);

        $this->actingAs($this->developer())
            ->putJson(route('explorer.context'), ['country' => 'NG', 'environment' => 'live'])
            ->assertJsonValidationErrors('environment');

        $this->actingAs(User::factory()->liveEnabled()->create())
            ->putJson(route('explorer.context'), ['country' => 'NG', 'environment' => 'live'])
            ->assertOk()
            ->assertJsonPath('context.environment', 'live');
    }

    public function test_stored_preference_is_restored_on_a_new_session(): void
    {
        $user = $this->developer();
        UserApiPreference::create(['user_id' => $user->id, 'country_code' => 'GH', 'currency_code' => 'GHS', 'environment' => 'test']);

        $this->actingAs($user)->get('/api-explorer')->assertSee('"country":"GH"', false);
    }

    public function test_endpoint_form_narrows_currency_and_channels_to_the_market(): void
    {
        $user = $this->developer();
        $this->actingAs($user)->putJson(route('explorer.context'), ['country' => 'GH', 'environment' => 'test']);

        $html = $this->actingAs($user)->get('/api-explorer/transactions/initialize')->assertOk()->getContent();

        preg_match('#<script type="application/json" id="endpoint-config">(.*?)</script>#s', $html, $m);
        $config = json_decode($m[1], true);

        $this->assertSame(['GHS'], $config['allowed']['currency']);
        $this->assertContains('mobile_money', $config['allowed']['channels']);
        $this->assertNotContains('ussd', $config['allowed']['channels'], 'USSD is documented for Nigeria only');
        $this->assertSame('GHS', $config['defaults']['currency']);
    }

    public function test_bank_list_uses_the_country_value_paystack_accepts(): void
    {
        $user = $this->developer();
        $this->actingAs($user)->putJson(route('explorer.context'), ['country' => 'ZA', 'environment' => 'test']);

        $html = $this->actingAs($user)->get('/api-explorer/banks/list')->getContent();
        preg_match('#id="endpoint-config">(.*?)</script>#s', $html, $m);

        $this->assertSame('south africa', json_decode($m[1], true)['defaults']['country']);
    }
}
