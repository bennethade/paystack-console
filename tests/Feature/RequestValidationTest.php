<?php

namespace Tests\Feature;

use App\Enums\PaystackEnvironment;
use App\Models\Endpoint;
use App\Models\PaystackCountry;
use App\Services\Paystack\EndpointValidator;
use App\Services\Paystack\ExplorerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RequestValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaystack();
    }

    private function context(string $country = 'NG', ?string $currency = null): ExplorerContext
    {
        $c = PaystackCountry::with('currencies', 'defaultCurrency')->where('code', $country)->firstOrFail();

        return new ExplorerContext($c, $currency ? $c->currencies->firstWhere('code', $currency) : $c->defaultCurrency, PaystackEnvironment::Test);
    }

    private function errors(string $key, array $input, ?ExplorerContext $context = null): array
    {
        try {
            app(EndpointValidator::class)->validate(Endpoint::where('key', $key)->firstOrFail(), $input, $context ?? $this->context());
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    }

    public function test_required_parameters_are_enforced_with_readable_messages(): void
    {
        $errors = $this->errors('transaction.initialize', []);

        $this->assertSame(['Email is required.'], $errors['params.email']);
        $this->assertSame(['Amount is required.'], $errors['params.amount']);
    }

    public function test_amount_must_be_a_positive_integer(): void
    {
        $this->assertSame(['Amount must be a positive integer.'], $this->errors('transaction.initialize', ['email' => 'a@b.co', 'amount' => -10])['params.amount']);
        $this->assertArrayHasKey('params.amount', $this->errors('transaction.initialize', ['email' => 'a@b.co', 'amount' => '12.5']));
    }

    public function test_email_and_url_formats(): void
    {
        $errors = $this->errors('transaction.initialize', ['email' => 'not-an-email', 'amount' => 5000, 'callback_url' => 'javascript:alert(1)']);

        $this->assertArrayHasKey('params.email', $errors);
        $this->assertArrayHasKey('params.callback_url', $errors);
    }

    public function test_currency_must_be_valid_for_the_selected_country(): void
    {
        $errors = $this->errors('transaction.initialize', ['email' => 'a@b.co', 'amount' => 5000, 'currency' => 'KES']);

        $this->assertSame(['Currency is invalid for the selected country (Nigeria).'], $errors['params.currency']);
        $this->assertSame([], $this->errors('transaction.initialize', ['email' => 'a@b.co', 'amount' => 5000, 'currency' => 'USD']));
    }

    public function test_channels_are_narrowed_to_the_market(): void
    {
        $errors = $this->errors('transaction.initialize', ['email' => 'a@b.co', 'amount' => 5000, 'channels' => ['card', 'eft']]);
        $this->assertSame(['Channel eft is not available in Nigeria.'], $errors['params.channels.1']);

        $za = $this->context('ZA');
        $this->assertSame([], $this->errors('transaction.initialize', ['email' => 'a@b.co', 'amount' => 5000, 'channels' => ['card', 'eft']], $za));
    }

    public function test_recipient_type_is_narrowed_to_the_market(): void
    {
        $input = ['type' => 'nuban', 'name' => 'Jane', 'account_number' => '0001234567', 'bank_code' => '058'];

        $this->assertSame([], $this->errors('transferrecipient.create', $input));
        $this->assertArrayHasKey('params.type', $this->errors('transferrecipient.create', $input, $this->context('GH')));
    }

    public function test_json_fields_must_be_valid_json(): void
    {
        $errors = $this->errors('transaction.initialize', ['email' => 'a@b.co', 'amount' => 5000, 'metadata' => '{"broken": ']);

        $this->assertStringContainsString('must be valid JSON', $errors['params.metadata'][0]);
    }

    public function test_values_are_cast_and_unknown_parameters_dropped(): void
    {
        $result = app(EndpointValidator::class)->validate(Endpoint::where('key', 'transaction.list')->firstOrFail(), [
            'perPage' => '20',
            'use_cursor' => 'true',
            'status' => 'success',
            'injected' => 'should not pass',
            'from' => '',
        ], $this->context());

        $this->assertSame(['use_cursor' => true, 'perPage' => 20, 'status' => 'success'], $result['values']);
    }

    public function test_stringified_json_fields_accept_objects(): void
    {
        $result = app(EndpointValidator::class)->validate(Endpoint::where('key', 'customer.create')->firstOrFail(), [
            'email' => 'a@b.co',
            'metadata' => ['tier' => 'gold'],
        ], $this->context());

        $this->assertSame('{"tier":"gold"}', $result['values']['metadata']);
    }

    public function test_enum_values_are_enforced(): void
    {
        $this->assertArrayHasKey('params.status', $this->errors('transaction.list', ['status' => 'bogus']));
    }

    public function test_below_minimum_amount_is_a_warning_not_an_error(): void
    {
        $result = app(EndpointValidator::class)->validate(Endpoint::where('key', 'transaction.initialize')->firstOrFail(), [
            'email' => 'a@b.co', 'amount' => 100,
        ], $this->context());

        $this->assertStringContainsString('below Paystack\'s documented transaction minimum', $result['warnings'][0]);
    }
}
