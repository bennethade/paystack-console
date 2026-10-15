<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\EndpointCategory;
use App\Services\Paystack\EndpointRegistry;
use App\Services\Paystack\OpenApi\EndpointImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaystack();
    }

    public function test_registry_contains_spec_and_reference_only_endpoints(): void
    {
        $this->assertGreaterThan(160, Endpoint::count());

        foreach (['transaction.initialize', 'transaction.verify', 'bank.list', 'customer.create', 'transfer.initiate',
            'subscription.disable', 'refund.create', 'dedicated_account.create', 'preauthorization.capture', 'capitec_pay.requery', 'webhook_events.list'] as $key) {
            $this->assertDatabaseHas('paystack_endpoints', ['key' => $key]);
        }
    }

    public function test_methods_and_paths_match_the_documentation(): void
    {
        $expected = [
            'transaction.initialize' => ['POST', '/transaction/initialize'],
            'transaction.verify' => ['GET', '/transaction/verify/{reference}'],
            'bank.list' => ['GET', '/bank'],
            'customer.update' => ['PUT', '/customer/{code}'],
            'transferrecipient.delete' => ['DELETE', '/transferrecipient/{code}'],
            'virtual_terminal.delete_split_code' => ['DELETE', '/virtual_terminal/{code}/split_code'],
            'preauthorization.verify' => ['GET', '/preauthorization/verify/{reference}'],
            'webhook_events.list' => ['GET', '/integration/webhooks/events'],
            'webhook_events.lookup' => ['GET', '/integration/webhooks/events/lookup'],
            'webhook_events.fetch' => ['GET', '/integration/webhooks/events/{id}'],
            'webhook_events.resend' => ['POST', '/integration/webhooks/events/resend'],
            'webhook_events.resend_matching' => ['POST', '/integration/webhooks/events/resend-matching'],
        ];

        foreach ($expected as $key => [$method, $path]) {
            $endpoint = Endpoint::where('key', $key)->firstOrFail();
            $this->assertSame($method, $endpoint->method, $key);
            $this->assertSame($path, $endpoint->path, $key);
        }
    }

    public function test_documented_overlay_corrections_are_applied(): void
    {
        $resolve = Endpoint::where('key', 'bank.resolve_account_number')->firstOrFail();
        $params = collect($resolve->parameters)->keyBy('name');
        $this->assertSame('string', $params['bank_code']['type'], 'bank codes keep leading zeros');
        $this->assertTrue($params['account_number']['required']);
        $this->assertSame(['resolve_account'], $resolve->required_features);

        $bank = Endpoint::where('key', 'bank.list')->firstOrFail();
        $this->assertSame('bank_table', $bank->workflow);
        $this->assertSame('bank_country', collect($bank->parameters)->firstWhere('name', 'country')['binding']);

        $address = Endpoint::where('key', 'charge.submit_address')->firstOrFail();
        $this->assertContains('zipcode', array_column($address->parameters, 'name'));

        $this->assertSame('public', Endpoint::where('key', 'capitec_pay.requery')->value('auth'));
        $this->assertTrue((bool) Endpoint::where('key', 'transfer.initiate')->value('is_dangerous'));
        $this->assertStringStartsWith('https://paystack.com/docs/api/transaction/#initialize', Endpoint::where('key', 'transaction.initialize')->value('docs_url'));
    }

    public function test_operations_missing_from_the_reference_pages_import_disabled(): void
    {
        $this->assertFalse((bool) Endpoint::where('key', 'transfer.export_transfer')->value('is_enabled'));
        $this->assertFalse((bool) Endpoint::where('key', 'product.delete')->value('is_documented'));
    }

    public function test_every_endpoint_has_a_unique_url_within_its_category(): void
    {
        $urls = Endpoint::with('category')->get()->map(fn (Endpoint $e) => $e->category->slug.'/'.$e->slug());

        $this->assertSame($urls->count(), $urls->unique()->count());
    }

    public function test_every_path_placeholder_has_a_path_parameter(): void
    {
        foreach (Endpoint::all() as $endpoint) {
            preg_match_all('/\{([^}]+)\}/', $endpoint->path, $m);
            $pathParams = collect($endpoint->parameters)->where('in', 'path')->pluck('name')->all();
            foreach ($m[1] as $placeholder) {
                $this->assertContains($placeholder, $pathParams, "{$endpoint->key} is missing path parameter {$placeholder}");
            }
        }
    }

    public function test_search_finds_endpoints_by_words_aliases_and_paths(): void
    {
        $registry = app(EndpointRegistry::class);

        $this->assertSame('transaction.initialize', $registry->search('transaction initialize')->first()['key']);
        $this->assertTrue($registry->search('banks')->pluck('key')->contains('bank.list'));
        $this->assertTrue($registry->search('dva')->pluck('key')->contains('dedicated_account.create'));
        $this->assertTrue($registry->search('/refund')->isNotEmpty());
        $this->assertTrue($registry->search('zzzz-nothing')->isEmpty());
    }

    public function test_search_endpoint_returns_json(): void
    {
        $this->actingAs($this->developer())
            ->getJson(route('explorer.search', ['q' => 'refund']))
            ->assertOk()
            ->assertJsonPath('results.0.category', 'Refunds');
    }

    public function test_disabled_endpoints_and_categories_are_hidden(): void
    {
        $user = $this->developer();
        $this->actingAs($user)->get('/api-explorer/transactions/initialize')->assertOk();

        Endpoint::where('key', 'transaction.initialize')->update(['is_enabled' => false]);
        app(EndpointRegistry::class)->flush();
        $this->actingAs($user)->get('/api-explorer/transactions/initialize')->assertNotFound();
        $this->actingAs($user)->execute('transaction.initialize', ['email' => 'a@b.co', 'amount' => 5000])->assertNotFound();

        EndpointCategory::where('slug', 'banks')->update(['is_enabled' => false]);
        app(EndpointRegistry::class)->flush();
        $this->actingAs($user)->get('/api-explorer/banks/list')->assertNotFound();
    }

    public function test_reimport_preserves_admin_edits_unless_forced(): void
    {
        $endpoint = Endpoint::where('key', 'plan.create')->firstOrFail();
        $endpoint->update(['name' => 'My Plan Creator', 'is_locally_modified' => true]);

        $stats = app(EndpointImporter::class)->import(config('paystack.openapi.spec_path'));
        $this->assertSame(1, $stats['preserved']);
        $this->assertSame('My Plan Creator', $endpoint->fresh()->name);

        app(EndpointImporter::class)->import(config('paystack.openapi.spec_path'), force: true);
        $this->assertSame('Create Plan', $endpoint->fresh()->name);
    }

    public function test_import_command_dry_run_writes_nothing(): void
    {
        Endpoint::query()->delete();

        $this->artisan('paystack:import-openapi', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Endpoint::count());

        $this->artisan('paystack:import-openapi')->assertSuccessful();
        $this->assertGreaterThan(160, Endpoint::count());
    }

    public function test_endpoint_page_documents_required_and_optional_parameters(): void
    {
        $this->actingAs($this->developer())
            ->get('/api-explorer/transactions/initialize')
            ->assertOk()
            ->assertSee('Initialize Transaction')
            ->assertSee('/transaction/initialize')
            ->assertSee('Required')
            ->assertSee('Optional')
            ->assertSee('https://paystack.com/docs/api/transaction/#initialize');
    }
}
