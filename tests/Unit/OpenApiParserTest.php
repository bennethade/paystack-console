<?php

namespace Tests\Unit;

use App\Services\Paystack\OpenApi\OpenApiParser;
use PHPUnit\Framework\TestCase;

class OpenApiParserTest extends TestCase
{
    private function spec(): array
    {
        return [
            'openapi' => '3.0.1',
            'tags' => [['name' => 'Thing', 'x-product-name' => 'Things', 'description' => 'Thing endpoints']],
            'paths' => [
                '/thing/{id}' => [
                    'get' => [
                        'tags' => ['Thing'],
                        'summary' => 'Fetch Thing',
                        'operationId' => 'thing_fetchOne',
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                            ['name' => 'perPage', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'X-Trace', 'in' => 'header', 'schema' => ['type' => 'string']],
                        ],
                    ],
                ],
                '/thing' => [
                    'post' => [
                        'tags' => ['Thing'],
                        'summary' => 'Create Thing',
                        'operationId' => 'thing_createNew',
                        'requestBody' => ['content' => ['application/json' => ['schema' => [
                            'allOf' => [
                                ['$ref' => '#/components/schemas/Base'],
                                ['type' => 'object', 'required' => ['amount'], 'properties' => ['amount' => ['type' => 'integer']]],
                            ],
                        ]]]],
                    ],
                ],
                '/upload' => [
                    'post' => [
                        'tags' => ['Thing'],
                        'operationId' => 'thing_upload',
                        'requestBody' => ['content' => ['multipart/form-data' => ['schema' => [
                            'type' => 'object',
                            'properties' => ['file' => ['type' => 'string', 'format' => 'binary']],
                        ]]]],
                    ],
                ],
            ],
            'components' => ['schemas' => [
                'Base' => [
                    'type' => 'object',
                    'required' => ['email'],
                    'properties' => [
                        'email' => ['type' => 'string', 'description' => 'Email'],
                        'currency' => ['$ref' => '#/components/schemas/Currency'],
                        'channels' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['card', 'bank']]],
                        'metadata' => ['type' => 'object', 'properties' => ['note' => ['type' => 'string']]],
                    ],
                ],
                'Currency' => ['type' => 'string', 'enum' => ['NGN', 'GHS']],
            ]],
        ];
    }

    public function test_it_parses_operations_tags_and_keys(): void
    {
        $result = (new OpenApiParser)->parse($this->spec());

        $this->assertSame('Things', $result['tags']['Thing']['name']);
        $this->assertCount(3, $result['endpoints']);

        $fetch = $result['endpoints'][0];
        $this->assertSame('thing.fetch_one', $fetch['key']);
        $this->assertSame('GET', $fetch['method']);
        $this->assertSame('none', $fetch['body_type']);
        $this->assertSame('page', $fetch['pagination']);
        $this->assertSame(['id', 'perPage', 'page'], array_column($fetch['parameters'], 'name'), 'header params are skipped');
        $this->assertTrue($fetch['parameters'][0]['required']);
        $this->assertSame('path', $fetch['parameters'][0]['in']);
    }

    public function test_it_resolves_refs_and_merges_all_of(): void
    {
        $create = (new OpenApiParser)->parse($this->spec())['endpoints'][1];
        $params = collect($create['parameters'])->keyBy('name');

        $this->assertSame(['email', 'currency', 'channels', 'metadata', 'amount'], $params->keys()->all());
        $this->assertTrue($params['email']['required']);
        $this->assertTrue($params['amount']['required']);
        $this->assertSame(['NGN', 'GHS'], $params['currency']['enum']);
        $this->assertSame('currency', $params['currency']['binding']);
        $this->assertSame('email', $params['email']['format']);
        $this->assertSame('multiselect', $params['channels']['ui']);
        $this->assertSame('channels', $params['channels']['binding']);
        $this->assertSame('json', $params['metadata']['ui']);
        $this->assertSame('note', $params['metadata']['properties'][0]['name']);
    }

    public function test_multipart_binary_fields_become_file_inputs(): void
    {
        $upload = (new OpenApiParser)->parse($this->spec())['endpoints'][2];

        $this->assertSame('multipart', $upload['body_type']);
        $this->assertSame('file', $upload['parameters'][0]['type']);
        $this->assertSame('file', $upload['parameters'][0]['ui']);
    }

    public function test_key_generation(): void
    {
        $this->assertSame('transaction.charge_authorization', OpenApiParser::keyFromOperationId('transaction_chargeAuthorization'));
        $this->assertSame('dedicated_account.available_providers', OpenApiParser::keyFromOperationId('dedicatedAccount_availableProviders'));
    }

    public function test_unresolvable_refs_fail_loudly(): void
    {
        $spec = $this->spec();
        $spec['components']['schemas']['Base']['properties']['currency'] = ['$ref' => '#/components/schemas/Missing'];

        $this->expectException(\RuntimeException::class);
        (new OpenApiParser)->parse($spec);
    }

    public function test_the_bundled_paystack_spec_parses(): void
    {
        $result = (new OpenApiParser)->parseFile(dirname(__DIR__, 2).'/resources/openapi/paystack.yaml');

        $this->assertGreaterThan(150, count($result['endpoints']));
        $keys = array_column($result['endpoints'], 'key');
        $this->assertContains('transaction.initialize', $keys);
        $this->assertContains('bank.list', $keys);
        $this->assertSame(count($keys), count(array_unique($keys)), 'endpoint keys are unique');
    }
}
