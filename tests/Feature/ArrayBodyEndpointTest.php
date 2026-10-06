<?php

namespace Tests\Feature;

use App\Models\ApiRequestLog;
use App\Models\Endpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Endpoints whose request body is a JSON array rather than an object,
 * e.g. POST /bulkcharge (https://paystack.com/docs/api/bulk-charge/#initiate).
 */
class ArrayBodyEndpointTest extends TestCase
{
    use RefreshDatabase;

    private array $charges = [
        ['authorization' => 'AUTH_ncx8hews93', 'amount' => 2500, 'reference' => 'dam1266638dhhd'],
        ['authorization' => 'AUTH_xfuz7dy4b9', 'amount' => 1500, 'reference' => 'dam1266638dhhe'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaystack();
        $this->usePaystackKeys('NG');
    }

    public function test_bulk_charge_is_registered_with_a_whole_body_parameter(): void
    {
        $endpoint = Endpoint::where('key', 'bulk_charge.initiate')->firstOrFail();

        $this->assertCount(1, $endpoint->parameters);
        $body = $endpoint->parameters[0];
        $this->assertSame('body', $body['name']);
        $this->assertTrue($body['root']);
        $this->assertTrue($body['required']);
        $this->assertSame('json', $body['ui']);
        $this->assertSame(['authorization', 'amount', 'reference', 'attempt_partial_debit', 'at_least', 'metadata'], array_column($body['items']['properties'], 'name'));
        $this->assertSame($this->charges, $endpoint->example, 'documented sample is available in the form');
    }

    public function test_the_array_is_sent_as_the_entire_json_body(): void
    {
        Http::fake(['api.paystack.co/bulkcharge' => Http::response([
            'status' => true, 'message' => 'Charges have been queued', 'data' => ['batch_code' => 'BCH_rrsbgwb4ivgzst1'],
        ])]);

        $response = $this->actingAs($this->developer())->execute('bulk_charge.initiate', ['body' => $this->charges]);

        $response->assertOk()
            ->assertJsonPath('response.status', 200)
            ->assertJsonPath('request.body', $this->charges)
            ->assertJsonPath('request.body_type', 'json');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === 'https://api.paystack.co/bulkcharge'
            && json_decode($r->body(), true) === $this->charges
            && str_starts_with($r->body(), '['));

        $curl = $response->json('curl');
        $this->assertStringContainsString('"authorization": "AUTH_ncx8hews93"', $curl);
        $this->assertMatchesRegularExpression("/-d '\\[/", $curl, 'cURL body is a JSON array');

        $this->assertSame($this->charges, ApiRequestLog::firstOrFail()->request_body);
    }

    public function test_the_body_editor_text_is_accepted(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true])]);

        $this->actingAs($this->developer())->execute('bulk_charge.initiate', ['body' => json_encode($this->charges)])
            ->assertOk()
            ->assertJsonPath('request.body.1.amount', 1500);
    }

    public function test_items_are_validated_against_the_documented_fields(): void
    {
        Http::fake();
        $user = $this->developer();

        $this->actingAs($user)->execute('bulk_charge.initiate', [])
            ->assertJsonValidationErrors(['params.body' => 'Body is required.']);

        $this->actingAs($user)->execute('bulk_charge.initiate', ['body' => [
            ['authorization' => 'AUTH_x', 'amount' => 2500],
            ['amount' => -1],
            'not-an-object',
        ]])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'params.body.1.authorization' => 'Item 2: authorization is required.',
                'params.body.1.amount' => 'Item 2: amount must be a positive integer.',
                'params.body.2' => 'Body item 3 must be a JSON object.',
            ])
            ->assertJsonMissingValidationErrors(['params.body.0.authorization', 'params.body.0.amount']);

        Http::assertNothingSent();
    }

    public function test_endpoint_page_offers_the_body_editor(): void
    {
        $this->actingAs($this->developer())->get('/api-explorer/bulk-charges/initiate')
            ->assertOk()
            ->assertDontSee('This endpoint takes no parameters.')
            ->assertSee('Insert documented example')
            ->assertSee('"root":true', false);
    }
}
