<?php

namespace Tests\Feature;

use App\Services\Paystack\Data\PaystackRequest;
use App\Services\Paystack\PaystackClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackClientTest extends TestCase
{
    public function test_get_requests_send_query_and_bearer_token(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'message' => 'Banks retrieved', 'data' => []])]);

        $response = app(PaystackClient::class)->send(
            new PaystackRequest('GET', 'https://api.paystack.co', '/bank', ['country' => 'ghana', 'use_cursor' => true]),
            self::TEST_SECRET,
        );

        $this->assertSame(200, $response->status);
        $this->assertTrue($response->successful());
        $this->assertGreaterThanOrEqual(0, $response->durationMs);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://api.paystack.co/bank?')
                && $request['country'] === 'ghana'
                && $request['use_cursor'] === 'true'
                && $request->hasHeader('Authorization', 'Bearer '.self::TEST_SECRET);
        });
    }

    public function test_post_requests_send_json(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true], 200)]);

        app(PaystackClient::class)->send(
            new PaystackRequest('POST', 'https://api.paystack.co', '/transaction/initialize', [], ['email' => 'a@b.co', 'amount' => 5000, 'metadata' => ['x' => 1]]),
            self::TEST_SECRET,
        );

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->isJson()
            && $request->data() === ['email' => 'a@b.co', 'amount' => 5000, 'metadata' => ['x' => 1]]);
    }

    public function test_put_and_delete_are_supported(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true])]);
        $client = app(PaystackClient::class);

        $client->send(new PaystackRequest('PUT', 'https://api.paystack.co', '/customer/CUS_x', [], ['first_name' => 'Ada']), self::TEST_SECRET);
        $client->send(new PaystackRequest('DELETE', 'https://api.paystack.co', '/transferrecipient/RCP_x', [], [], [], 'none'), self::TEST_SECRET);

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['first_name'] === 'Ada');
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/transferrecipient/RCP_x'));
    }

    public function test_error_statuses_are_returned_not_thrown(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);

        $response = app(PaystackClient::class)->send(new PaystackRequest('GET', 'https://api.paystack.co', '/balance'), self::TEST_SECRET);

        $this->assertSame(401, $response->status);
        $this->assertFalse($response->successful());
        $this->assertSame('Invalid key', $response->json()['message']);
    }

    public function test_connection_failures_become_transport_errors_without_leaking_the_key(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28 while using '.self::TEST_SECRET));

        $response = app(PaystackClient::class)->send(new PaystackRequest('GET', 'https://api.paystack.co', '/balance'), self::TEST_SECRET);

        $this->assertNull($response->status);
        $this->assertStringContainsString('Could not connect to Paystack', $response->transportError);
        $this->assertStringNotContainsString(self::TEST_SECRET, $response->transportError);
    }

    public function test_multipart_requests_attach_files(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true])]);

        app(PaystackClient::class)->send(new PaystackRequest(
            'POST', 'https://api.paystack.co', '/upload', [], ['note' => 'hello'],
            ['file' => UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf')], 'multipart',
        ), self::TEST_SECRET);

        Http::assertSent(fn (Request $r) => $r->isMultipart()
            && collect($r->data())->contains(fn ($part) => ($part['name'] ?? null) === 'file' && ($part['filename'] ?? null) === 'evidence.pdf')
            && collect($r->data())->contains(fn ($part) => ($part['name'] ?? null) === 'note' && ($part['contents'] ?? null) === 'hello'));
    }
}
