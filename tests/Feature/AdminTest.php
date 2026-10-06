<?php

namespace Tests\Feature;

use App\Models\ApiRequestLog;
use App\Models\Endpoint;
use App\Models\EndpointCategory;
use App\Models\PaystackCountry;
use App\Models\PaystackCurrency;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaystack();
        $this->admin = $this->admin();
    }

    public function test_admin_can_disable_an_endpoint_without_code_changes(): void
    {
        $endpoint = Endpoint::where('key', 'refund.create')->firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.endpoints.toggle', $endpoint))->assertRedirect();

        $this->assertFalse($endpoint->fresh()->is_enabled);
        $this->actingAs($this->developer())->get('/api-explorer/refunds/create')->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.endpoint_toggled']);
    }

    public function test_admin_can_add_a_new_endpoint(): void
    {
        $category = EndpointCategory::where('slug', 'miscellaneous')->firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.endpoints.store'), [
            'key' => 'custom.lookup',
            'category_id' => $category->id,
            'name' => 'Custom Lookup',
            'method' => 'GET',
            'path' => '/custom/{id}',
            'auth' => 'secret',
            'body_type' => 'none',
            'parameters_json' => json_encode([['name' => 'id', 'in' => 'path', 'type' => 'string', 'required' => true]]),
            'is_enabled' => '1',
        ])->assertRedirect();

        $endpoint = Endpoint::where('key', 'custom.lookup')->firstOrFail();
        $this->assertSame('manual', $endpoint->source);
        $this->actingAs($this->developer())->get('/api-explorer/miscellaneous/lookup')->assertOk()->assertSee('Custom Lookup');
    }

    public function test_endpoint_parameters_are_validated(): void
    {
        $endpoint = Endpoint::where('key', 'transaction.verify')->firstOrFail();
        $category = $endpoint->category_id;

        $base = ['key' => $endpoint->key, 'category_id' => $category, 'name' => 'Verify', 'method' => 'GET', 'path' => '/transaction/verify/{reference}', 'auth' => 'secret', 'body_type' => 'none'];

        $this->actingAs($this->admin)->put(route('admin.endpoints.update', $endpoint), $base + ['parameters_json' => '{not json'])
            ->assertSessionHasErrors('parameters_json');
        $this->actingAs($this->admin)->put(route('admin.endpoints.update', $endpoint), $base + ['parameters_json' => '[]'])
            ->assertSessionHasErrors('parameters_json'); // {reference} placeholder needs a path param
        $this->actingAs($this->admin)->put(route('admin.endpoints.update', $endpoint), $base + ['parameters_json' => '[{"name":"x","in":"cookie"}]'])
            ->assertSessionHasErrors('parameters_json');
    }

    public function test_editing_an_imported_endpoint_marks_it_locally_modified(): void
    {
        $endpoint = Endpoint::where('key', 'transaction.verify')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.endpoints.update', $endpoint), [
            'key' => $endpoint->key, 'category_id' => $endpoint->category_id, 'name' => 'Verify (edited)',
            'method' => 'GET', 'path' => $endpoint->path, 'auth' => 'secret', 'body_type' => 'none',
            'parameters_json' => json_encode($endpoint->parameters), 'is_enabled' => '1', 'is_documented' => '1',
        ])->assertRedirect();

        $this->assertTrue($endpoint->fresh()->is_locally_modified);
        $this->assertSame('Verify (edited)', $endpoint->fresh()->name);
    }

    public function test_admin_can_disable_a_country(): void
    {
        $gh = PaystackCountry::where('code', 'GH')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.countries.update', $gh), [
            'code' => 'GH', 'name' => 'Ghana', 'currency_ids' => $gh->currencies->pluck('id')->all(),
            'default_currency_id' => $gh->default_currency_id, 'channels' => $gh->channels, 'features' => $gh->features,
            'recipient_types' => implode(',', $gh->recipient_types),
        ])->assertRedirect();

        $this->assertFalse($gh->fresh()->is_enabled);
        $this->actingAs($this->developer())->putJson(route('explorer.context'), ['country' => 'GH', 'environment' => 'test'])->assertUnprocessable();
    }

    public function test_country_default_currency_must_be_one_of_its_currencies(): void
    {
        $gh = PaystackCountry::where('code', 'GH')->firstOrFail();
        $ngn = PaystackCurrency::where('code', 'NGN')->value('id');

        $this->actingAs($this->admin)->put(route('admin.countries.update', $gh), [
            'code' => 'GH', 'name' => 'Ghana', 'currency_ids' => $gh->currencies->pluck('id')->all(), 'default_currency_id' => $ngn,
        ])->assertSessionHasErrors('default_currency_id');
    }

    public function test_settings_and_feature_flags_can_be_updated(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), [
            'registration_enabled' => '0',
            'mock_mode' => '1',
            'webhooks_enabled' => '1',
            'history_enabled' => '1',
            'default_role' => 'viewer',
            'history_retention_days' => 7,
            'announcement' => 'Maintenance tonight',
        ])->assertRedirect();

        $settings = app(Settings::class);
        $settings->flush();
        $this->assertFalse($settings->bool('registration_enabled'));
        $this->assertTrue($settings->mockMode());
        $this->assertSame('viewer', $settings->get('default_role'));
        $this->actingAs($this->developer())->get('/api-explorer')->assertSee('Maintenance tonight');
    }

    public function test_admin_can_suspend_users(): void
    {
        $user = $this->developer();

        $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'role' => 'developer', 'status' => 'suspended',
        ])->assertRedirect();

        $this->assertFalse($user->fresh()->isActive());
    }

    public function test_admin_sees_global_activity(): void
    {
        ApiRequestLog::create(['user_id' => $this->developer()->id, 'endpoint_key' => 'bank.list', 'environment' => 'test', 'method' => 'GET', 'path' => '/bank', 'url' => 'x']);

        $this->actingAs($this->admin)->get(route('admin.logs.index'))->assertOk()->assertSee('/bank');
    }

    public function test_spec_reimport_from_admin(): void
    {
        $this->actingAs($this->admin)->post(route('admin.endpoints.import'))->assertSessionHas('status');
    }
}
