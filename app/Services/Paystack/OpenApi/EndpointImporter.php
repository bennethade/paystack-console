<?php

namespace App\Services\Paystack\OpenApi;

use App\Models\Endpoint;
use App\Models\EndpointCategory;
use App\Services\Paystack\Data\ParameterDefinition;
use App\Services\Paystack\EndpointRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * OpenAPI document → overlay (config/paystack-endpoints.php) → database.
 *
 * Endpoints an admin has edited (is_locally_modified) keep their edits on
 * re-import unless $force is set.
 */
class EndpointImporter
{
    public function __construct(
        private readonly OpenApiParser $parser,
        private readonly EndpointRegistry $registry,
    ) {}

    /**
     * Build the final endpoint definitions without touching the database.
     *
     * @return array{categories: array<string, array>, endpoints: list<array>}
     */
    public function build(string $specPath, ?array $overlay = null): array
    {
        $overlay ??= config('paystack-endpoints');
        $parsed = $this->parser->parseFile($specPath);

        $categories = $overlay['categories'] ?? [];
        foreach ($parsed['tags'] as $tag => $meta) {
            $categories[$tag] ??= ['slug' => Str::slug($meta['name']), 'name' => $meta['name'], 'sort' => 900];
            $categories[$tag]['description'] ??= $meta['description'];
        }

        $endpoints = [];
        foreach ($parsed['endpoints'] as $index => $definition) {
            $endpoints[] = $this->applyOverlay($definition, $overlay, $index);
        }

        foreach ($overlay['manual'] ?? [] as $index => $manual) {
            $endpoints[] = $this->normaliseManual($manual, 1000 + $index);
        }

        foreach ($endpoints as $endpoint) {
            $categories[$endpoint['category']] ??= ['slug' => Str::slug($endpoint['category']), 'name' => $endpoint['category'], 'sort' => 900];
        }

        return ['categories' => $categories, 'endpoints' => $endpoints];
    }

    /**
     * @return array{created: int, updated: int, preserved: int, categories: int}
     */
    public function import(string $specPath, bool $force = false, ?array $overlay = null): array
    {
        return $this->importBuilt($this->build($specPath, $overlay), $force);
    }

    /**
     * Persist definitions produced by build().
     *
     * @return array{created: int, updated: int, preserved: int, categories: int}
     */
    public function importBuilt(array $built, bool $force = false): array
    {
        $docsBase = rtrim(config('paystack.openapi.docs_base'), '/');
        $stats = ['created' => 0, 'updated' => 0, 'preserved' => 0, 'categories' => 0];

        DB::transaction(function () use ($built, $docsBase, $force, &$stats) {
            $categoryIds = [];
            foreach ($built['categories'] as $tag => $meta) {
                $category = EndpointCategory::firstOrNew(['slug' => $meta['slug']]);
                $isNew = ! $category->exists;
                $category->fill([
                    'name' => $isNew || $force ? $meta['name'] : $category->name,
                    'description' => $isNew || $force ? ($meta['description'] ?? null) : $category->description,
                    'docs_url' => isset($meta['docs']) ? "{$docsBase}/{$meta['docs']}" : $category->docs_url,
                    'sort_order' => $isNew || $force ? ($meta['sort'] ?? 900) : $category->sort_order,
                ]);
                if ($isNew) {
                    $category->is_enabled = true;
                }
                $category->save();
                $categoryIds[$tag] = $category->id;
                $stats['categories']++;
            }

            foreach ($built['endpoints'] as $definition) {
                $endpoint = Endpoint::firstOrNew(['key' => $definition['key']]);

                if ($endpoint->exists && $endpoint->is_locally_modified && ! $force) {
                    $stats['preserved']++;

                    continue;
                }

                $isNew = ! $endpoint->exists;
                $endpoint->fill([
                    'operation_id' => $definition['operation_id'],
                    'category_id' => $categoryIds[$definition['category']],
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'method' => $definition['method'],
                    'path' => $definition['path'],
                    'auth' => $definition['auth'],
                    'body_type' => $definition['body_type'],
                    'parameters' => $definition['parameters'],
                    'example' => $definition['example'],
                    'workflow' => $definition['workflow'],
                    'pagination' => $definition['pagination'],
                    'required_features' => $definition['features'] ?: null,
                    'supported_currencies' => $definition['currencies'] ?: null,
                    'is_dangerous' => $definition['dangerous'],
                    'is_documented' => $definition['documented'],
                    'notes' => $definition['notes'],
                    'docs_url' => $definition['docs'] ? "{$docsBase}/{$definition['docs']}" : null,
                    'source' => $definition['source'],
                    'sort_order' => $definition['sort_order'],
                    'is_locally_modified' => false,
                ]);

                // Only set enabled state on first import so admin toggles survive.
                if ($isNew || $force) {
                    $endpoint->is_enabled = $definition['enabled'];
                }

                $endpoint->save();
                $stats[$isNew ? 'created' : 'updated']++;
            }
        });

        $this->registry->flush();

        return $stats;
    }

    private function applyOverlay(array $definition, array $overlay, int $index): array
    {
        $override = $overlay['endpoints'][$definition['key']] ?? [];
        $parameters = $definition['parameters'];

        foreach ($override['params'] ?? [] as $name => $changes) {
            $position = array_search($name, array_column($parameters, 'name'), true);
            if ($position === false) {
                continue;
            }
            if ($changes === null) {
                array_splice($parameters, $position, 1);
            } else {
                $parameters[$position] = array_merge($parameters[$position], $changes);
            }
        }

        foreach ($override['rename'] ?? [] as $from => $to) {
            foreach ($parameters as &$parameter) {
                if ($parameter['name'] === $from) {
                    $parameter['name'] = $to;
                }
            }
            unset($parameter);
        }

        $existing = array_column($parameters, 'name');
        foreach ($override['add'] ?? [] as $extra) {
            if (! in_array($extra['name'], $existing, true)) {
                $parameters[] = $extra;
            }
        }

        $bodyType = $override['body_type'] ?? $definition['body_type'];

        return [
            'key' => $definition['key'],
            'operation_id' => $definition['operation_id'],
            'category' => $override['category'] ?? $definition['tag'],
            'name' => $override['name'] ?? $definition['name'],
            'description' => $override['description'] ?? $definition['description'],
            'method' => $definition['method'],
            'path' => $definition['path'],
            'auth' => $override['auth'] ?? $definition['auth'],
            'body_type' => $bodyType,
            'parameters' => $this->normaliseParameters($parameters),
            'example' => $override['example'] ?? $definition['example'],
            'workflow' => $override['workflow'] ?? null,
            'pagination' => $this->paginationFor($parameters, $definition['pagination']),
            'features' => $override['features'] ?? [],
            'currencies' => $override['currencies'] ?? [],
            'dangerous' => (bool) ($override['dangerous'] ?? false),
            'documented' => (bool) ($override['documented'] ?? true),
            'enabled' => (bool) ($override['enabled'] ?? true),
            'notes' => $override['notes'] ?? null,
            'docs' => $overlay['docs'][$definition['operation_id']] ?? ($overlay['categories'][$definition['tag']]['docs'] ?? null),
            'source' => 'openapi',
            'sort_order' => $index,
        ];
    }

    private function normaliseManual(array $manual, int $sort): array
    {
        $method = strtoupper($manual['method']);

        return [
            'key' => $manual['key'],
            'operation_id' => null,
            'category' => $manual['category'],
            'name' => $manual['name'],
            'description' => $manual['description'] ?? null,
            'method' => $method,
            'path' => $manual['path'],
            'auth' => $manual['auth'] ?? 'secret',
            'body_type' => $manual['body_type'] ?? ($method === 'GET' ? 'none' : 'json'),
            'parameters' => $this->normaliseParameters($manual['parameters'] ?? []),
            'example' => $manual['example'] ?? null,
            'workflow' => $manual['workflow'] ?? null,
            'pagination' => $this->paginationFor($manual['parameters'] ?? [], null),
            'features' => $manual['features'] ?? [],
            'currencies' => $manual['currencies'] ?? [],
            'dangerous' => (bool) ($manual['dangerous'] ?? false),
            'documented' => true,
            'enabled' => (bool) ($manual['enabled'] ?? true),
            'notes' => $manual['notes'] ?? null,
            'docs' => $manual['docs'] ?? null,
            'source' => 'manual',
            'sort_order' => $sort,
        ];
    }

    /**
     * Round-trip through ParameterDefinition so every stored parameter has a
     * consistent shape (and a computed `ui`).
     */
    private function normaliseParameters(array $parameters): array
    {
        return array_values(array_map(
            fn (array $p) => ParameterDefinition::fromArray($p)->toArray(),
            $parameters
        ));
    }

    private function paginationFor(array $parameters, ?string $detected): ?string
    {
        $names = array_column(array_filter($parameters, fn ($p) => ($p['in'] ?? null) === 'query'), 'name');
        $page = in_array('page', $names, true) && (in_array('perPage', $names, true) || in_array('per_page', $names, true));
        $cursor = in_array('use_cursor', $names, true) || in_array('next', $names, true);

        return match (true) {
            $page && $cursor => 'both',
            $page => 'page',
            $cursor => 'cursor',
            default => $detected,
        };
    }
}
