<?php

namespace App\Services\Paystack\OpenApi;

use App\Services\Paystack\Data\ParameterDefinition;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Converts an OpenAPI 3 document (YAML or JSON) into normalised endpoint
 * definitions for the registry. It is deliberately independent of the
 * database so it can be unit-tested and used for dry runs.
 */
class OpenApiParser
{
    private const MAX_DEPTH = 6;

    private array $spec = [];

    public function parseFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("OpenAPI document not found or unreadable: {$path}");
        }

        $contents = file_get_contents($path);
        $document = str_ends_with(strtolower($path), '.json')
            ? json_decode($contents, true, 512, JSON_THROW_ON_ERROR)
            : Yaml::parse($contents);

        return $this->parse($document);
    }

    /**
     * @return array{tags: array<string, array{name: string, description: ?string}>, endpoints: list<array<string, mixed>>}
     */
    public function parse(array $document): array
    {
        if (! isset($document['paths']) || ! is_array($document['paths'])) {
            throw new RuntimeException('The OpenAPI document has no paths.');
        }

        $this->spec = $document;

        $tags = [];
        foreach ($document['tags'] ?? [] as $tag) {
            $tags[$tag['name']] = [
                'name' => $tag['x-product-name'] ?? $tag['name'],
                'description' => isset($tag['description']) ? trim($tag['description']) : null,
            ];
        }

        $endpoints = [];
        foreach ($document['paths'] as $path => $pathItem) {
            $shared = $pathItem['parameters'] ?? [];

            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (! isset($pathItem[$method])) {
                    continue;
                }

                $endpoints[] = $this->parseOperation(strtoupper($method), $path, $pathItem[$method], $shared);
            }
        }

        return ['tags' => $tags, 'endpoints' => $endpoints];
    }

    private function parseOperation(string $method, string $path, array $operation, array $sharedParameters): array
    {
        $parameters = [];

        foreach (array_merge($sharedParameters, $operation['parameters'] ?? []) as $raw) {
            $raw = $this->resolve($raw);
            if (! in_array($raw['in'] ?? null, ['path', 'query'], true)) {
                continue; // headers / cookies are handled by the client
            }

            $schema = $this->resolve($raw['schema'] ?? []);
            $parameters[] = $this->toParameter(
                $raw['name'],
                $raw['in'],
                $schema,
                (bool) ($raw['required'] ?? $raw['in'] === 'path'),
                $raw['description'] ?? $schema['description'] ?? null,
                $raw['example'] ?? $schema['example'] ?? null,
            );
        }

        [$bodyParameters, $bodyType, $example] = $this->parseRequestBody($operation['requestBody'] ?? null);
        $parameters = array_merge($parameters, $bodyParameters);

        $tag = $operation['tags'][0] ?? 'Other';
        $operationId = $operation['operationId'] ?? strtolower($method).'_'.trim(str_replace(['/', '{', '}'], ['_', '', ''], $path), '_');

        return [
            'key' => self::keyFromOperationId($operationId),
            'operation_id' => $operationId,
            'tag' => $tag,
            'name' => $operation['summary'] ?? $operationId,
            'description' => isset($operation['description']) ? trim($operation['description']) : null,
            'method' => $method,
            'path' => $path,
            'auth' => isset($operation['security']) && $operation['security'] === [] ? 'none' : 'secret',
            'body_type' => $method === 'GET' ? 'none' : $bodyType,
            'parameters' => $parameters,
            'example' => $example,
            'pagination' => $this->detectPagination($parameters),
        ];
    }

    /**
     * @return array{0: list<array>, 1: string, 2: ?array}
     */
    private function parseRequestBody(?array $requestBody): array
    {
        if ($requestBody === null) {
            return [[], 'none', null];
        }

        $requestBody = $this->resolve($requestBody);
        $content = $requestBody['content'] ?? [];

        $bodyType = 'json';
        $media = $content['application/json'] ?? null;
        if ($media === null && isset($content['multipart/form-data'])) {
            $media = $content['multipart/form-data'];
            $bodyType = 'multipart';
        }
        $media ??= reset($content) ?: [];

        $schema = $this->flatten($this->resolve($media['schema'] ?? []));

        // The body itself is a JSON array (e.g. POST /bulkcharge): model it as
        // one required "root" parameter whose value is sent as the whole body.
        if (($schema['type'] ?? null) === 'array') {
            $items = $this->describeItems($schema['items'] ?? []);
            $itemSchema = $this->flatten($this->resolve($schema['items'] ?? []));

            $parameter = (new ParameterDefinition(
                name: 'body',
                in: 'body',
                type: 'array',
                required: true,
                description: trim(($schema['description'] ?? $itemSchema['description'] ?? 'List of items').' (sent as the entire JSON request body).'),
                items: $items,
                root: true,
            ))->toArray();

            $example = $media['example'] ?? $schema['example'] ?? null;

            return [[$parameter], $bodyType, is_array($example) ? $example : null];
        }

        $required = $schema['required'] ?? [];

        $parameters = [];
        foreach ($schema['properties'] ?? [] as $name => $propertySchema) {
            $propertySchema = $this->flatten($this->resolve($propertySchema));
            $parameters[] = $this->toParameter(
                $name,
                'body',
                $propertySchema,
                in_array($name, $required, true),
                $propertySchema['description'] ?? null,
                $propertySchema['example'] ?? ($schema['example'][$name] ?? null),
                $bodyType === 'multipart',
            );
        }

        $example = $media['example'] ?? $schema['example'] ?? null;

        return [$parameters, $bodyType, is_array($example) ? $example : null];
    }

    private function toParameter(string $name, string $in, array $schema, bool $required, ?string $description, mixed $example, bool $multipart = false): array
    {
        $schema = $this->flatten($schema);
        $type = $schema['type'] ?? (isset($schema['properties']) ? 'object' : 'string');

        if ($type === 'string' && in_array($schema['format'] ?? null, ['binary', 'base64'], true) && $multipart) {
            $type = 'file';
        }

        $definition = new ParameterDefinition(
            name: $name,
            in: $in,
            type: in_array($type, ParameterDefinition::TYPES, true) ? $type : 'string',
            format: $this->inferFormat($name, $schema),
            required: $required,
            description: $description !== null ? trim($description) : null,
            enum: $schema['enum'] ?? null,
            default: $schema['default'] ?? null,
            example: $example,
            items: $type === 'array' ? $this->describeItems($schema['items'] ?? []) : null,
            properties: $type === 'object' ? $this->describeProperties($schema, 1) : [],
            binding: $this->inferBinding($name, $schema),
            sensitive: in_array(strtolower($name), ['pin', 'otp', 'cvv', 'password'], true),
        );

        return $definition->toArray();
    }

    private function describeItems(array $items): array
    {
        $items = $this->flatten($this->resolve($items));
        $type = $items['type'] ?? (isset($items['properties']) ? 'object' : 'string');

        return array_filter([
            'type' => $type,
            'enum' => $items['enum'] ?? null,
            'properties' => $type === 'object' ? $this->describeProperties($items, 1) : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Nested object properties, kept for documentation and JSON templates.
     */
    private function describeProperties(array $schema, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $required = $schema['required'] ?? [];
        $properties = [];

        foreach ($schema['properties'] ?? [] as $name => $property) {
            $property = $this->flatten($this->resolve($property));
            $type = $property['type'] ?? (isset($property['properties']) ? 'object' : 'string');

            $properties[] = array_filter([
                'name' => $name,
                'type' => $type,
                'required' => in_array($name, $required, true) ?: null,
                'description' => isset($property['description']) ? trim($property['description']) : null,
                'enum' => $property['enum'] ?? null,
                'properties' => $type === 'object' ? ($this->describeProperties($property, $depth + 1) ?: null) : null,
                'items' => $type === 'array' ? $this->describeItems($property['items'] ?? []) : null,
            ], fn ($v) => $v !== null);
        }

        return $properties;
    }

    private function inferFormat(string $name, array $schema): ?string
    {
        if (isset($schema['format']) && in_array($schema['format'], ['email', 'uri', 'date', 'date-time'], true)) {
            return $schema['format'];
        }

        return match (true) {
            $name === 'email' => 'email',
            str_ends_with($name, '_url') => 'uri',
            default => null,
        };
    }

    private function inferBinding(string $name, array $schema): ?string
    {
        return match ($name) {
            'currency' => 'currency',
            'channels' => ($schema['type'] ?? null) === 'array' ? 'channels' : null,
            'callback_url' => 'callback_url',
            default => null,
        };
    }

    private function detectPagination(array $parameters): ?string
    {
        $names = array_column(array_filter($parameters, fn ($p) => $p['in'] === 'query'), 'name');
        $page = in_array('page', $names, true) && (in_array('perPage', $names, true) || in_array('per_page', $names, true));
        $cursor = in_array('use_cursor', $names, true) || in_array('next', $names, true);

        return match (true) {
            $page && $cursor => 'both',
            $page => 'page',
            $cursor => 'cursor',
            default => null,
        };
    }

    /**
     * Merge allOf members; take the first variant of oneOf/anyOf.
     */
    private function flatten(array $schema, int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            return $schema;
        }

        if (isset($schema['allOf'])) {
            $merged = array_diff_key($schema, ['allOf' => true]);
            foreach ($schema['allOf'] as $part) {
                $part = $this->flatten($this->resolve($part), $depth + 1);
                $merged['properties'] = array_merge($merged['properties'] ?? [], $part['properties'] ?? []);
                $merged['required'] = array_values(array_unique(array_merge($merged['required'] ?? [], $part['required'] ?? [])));
                foreach (['type', 'description', 'example'] as $key) {
                    if (! isset($merged[$key]) && isset($part[$key])) {
                        $merged[$key] = $part[$key];
                    }
                }
            }
            $schema = $merged;
        }

        foreach (['oneOf', 'anyOf'] as $keyword) {
            if (isset($schema[$keyword]) && ! isset($schema['type'])) {
                $first = $this->flatten($this->resolve($schema[$keyword][0] ?? []), $depth + 1);
                $schema = array_merge($first, array_diff_key($schema, [$keyword => true]));
            }
        }

        return $schema;
    }

    private function resolve(array $node, int $depth = 0): array
    {
        if (! isset($node['$ref']) || $depth > self::MAX_DEPTH) {
            return $node;
        }

        $ref = $node['$ref'];
        if (! str_starts_with($ref, '#/')) {
            throw new RuntimeException("External \$ref is not supported: {$ref}");
        }

        $target = $this->spec;
        foreach (explode('/', substr($ref, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (! is_array($target) || ! array_key_exists($segment, $target)) {
                throw new RuntimeException("Unresolvable \$ref: {$ref}");
            }
            $target = $target[$segment];
        }

        $siblings = array_diff_key($node, ['$ref' => true]);

        return array_merge($this->resolve($target, $depth + 1), $siblings);
    }

    /**
     * transaction_chargeAuthorization → transaction.charge_authorization
     */
    public static function keyFromOperationId(string $operationId): string
    {
        $parts = explode('_', $operationId, 2);
        $snake = fn (string $s) => strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $s));

        return count($parts) === 2
            ? $snake($parts[0]).'.'.$snake($parts[1])
            : $snake($operationId);
    }
}
