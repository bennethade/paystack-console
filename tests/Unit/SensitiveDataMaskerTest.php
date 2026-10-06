<?php

namespace Tests\Unit;

use App\Support\SensitiveDataMasker;
use PHPUnit\Framework\TestCase;

class SensitiveDataMaskerTest extends TestCase
{
    private function masker(): SensitiveDataMasker
    {
        return new SensitiveDataMasker(['authorization', 'password', 'pin', 'otp', 'cvv', 'secret_key']);
    }

    public function test_sensitive_keys_are_masked_recursively(): void
    {
        $masked = $this->masker()->mask([
            'reference' => 'abc',
            'pin' => '1234',
            'nested' => ['OTP' => '123456', 'card' => ['number' => '4084084084084081', 'cvv' => '408']],
        ]);

        $this->assertSame('abc', $masked['reference']);
        $this->assertSame('********', $masked['pin']);
        $this->assertSame('********', $masked['nested']['OTP']);
        $this->assertSame('408408******4081', $masked['nested']['card']['number']);
        $this->assertSame('********', $masked['nested']['card']['cvv']);
    }

    public function test_paystack_keys_are_masked_inside_any_string(): void
    {
        $this->assertSame(
            'key sk_test_******** and pk_live_********',
            $this->masker()->maskString('key sk_test_abc123DEF and pk_live_xyz789')
        );
    }

    public function test_authorization_header_is_always_masked(): void
    {
        $headers = $this->masker()->maskHeaders(['Authorization' => 'Bearer sk_test_real', 'Content-Type' => 'application/json']);

        $this->assertSame('Bearer ****************', $headers['Authorization']);
        $this->assertSame('application/json', $headers['Content-Type']);
    }

    public function test_empty_values_are_left_alone(): void
    {
        $this->assertSame(['pin' => null], $this->masker()->mask(['pin' => null]));
    }
}
