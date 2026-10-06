<?php

namespace Tests\Unit;

use App\Services\Paystack\CurlGenerator;
use App\Services\Paystack\Data\PaystackRequest;
use PHPUnit\Framework\TestCase;

class CurlGeneratorTest extends TestCase
{
    public function test_post_json_command_uses_a_placeholder_key(): void
    {
        $curl = (new CurlGenerator)->generate(new PaystackRequest(
            'POST', 'https://api.paystack.co', '/transaction/initialize', [], ['email' => "o'brien@example.com", 'amount' => 500000, 'currency' => 'NGN'],
        ));

        $this->assertStringStartsWith("curl -X POST 'https://api.paystack.co/transaction/initialize'", $curl);
        $this->assertStringContainsString("-H 'Authorization: Bearer YOUR_SECRET_KEY'", $curl);
        $this->assertStringContainsString("-H 'Content-Type: application/json'", $curl);
        $this->assertStringContainsString('"amount": 500000', $curl);
        $this->assertStringContainsString("o'\\''brien@example.com", $curl, 'single quotes are shell-escaped');
    }

    public function test_get_command_includes_query_and_no_body(): void
    {
        $curl = (new CurlGenerator)->generate(new PaystackRequest('GET', 'https://api.paystack.co', '/bank', ['country' => 'south africa', 'use_cursor' => true]));

        $this->assertStringContainsString("'https://api.paystack.co/bank?country=south%20africa&use_cursor=true'", $curl);
        $this->assertStringNotContainsString('-d ', $curl);
    }

    public function test_public_key_endpoints_use_the_public_placeholder(): void
    {
        $curl = (new CurlGenerator)->generate(new PaystackRequest('POST', 'https://api.paystack.co', '/capitec-pay/requery/ref', bodyType: 'none', auth: 'public'));

        $this->assertStringContainsString('YOUR_PUBLIC_KEY', $curl);
    }
}
