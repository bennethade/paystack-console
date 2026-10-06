<?php

namespace App\Services\Paystack;

use App\Models\Endpoint;
use App\Models\EndpointCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read side of the endpoint registry. Everything the UI shows about
 * endpoints (sidebar, search, forms, docs) comes from here, so adding an
 * endpoint is a data change, not a code change.
 */
class EndpointRegistry
{
    private const CACHE_KEY = 'paystack.registry.navigation';

    /**
     * Enabled categories with their enabled endpoints, for the sidebar.
     *
     * @return Collection<int, array{slug: string, name: string, endpoints: list<array>}>
     */
    public function navigation(): Collection
    {
        // Cached as a plain array: Laravel 13 refuses to unserialize arbitrary
        // objects from the cache store.
        return collect(Cache::remember(self::CACHE_KEY, 3600, function () {
            return EndpointCategory::query()
                ->enabled()
                ->ordered()
                ->with(['endpoints' => fn ($q) => $q->where('is_enabled', true)->orderBy('sort_order')])
                ->get()
                ->filter(fn (EndpointCategory $c) => $c->endpoints->isNotEmpty())
                ->map(fn (EndpointCategory $c) => [
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'endpoints' => $c->endpoints->map(fn (Endpoint $e) => $this->summary($e->setRelation('category', $c), $c->name))->all(),
                ])
                ->values()
                ->all();
        }));
    }

    /**
     * Flat list used by the global search box.
     *
     * @return list<array>
     */
    public function searchIndex(): array
    {
        return $this->navigation()->flatMap(fn (array $category) => $category['endpoints'])->values()->all();
    }

    /**
     * Server-side search across name, key, path, method, category and description.
     *
     * @return Collection<int, array>
     */
    public function search(string $query, int $limit = 20): Collection
    {
        $terms = array_filter(preg_split('/\s+/', strtolower(trim($query))));
        if ($terms === []) {
            return collect();
        }

        $aliases = ['dva' => 'dedicated', 'nuban' => 'dedicated', 'bank' => 'bank'];

        return collect($this->searchIndex())
            ->map(function (array $endpoint) use ($terms, $aliases) {
                $haystack = strtolower(implode(' ', [
                    $endpoint['name'], $endpoint['key'], $endpoint['path'], $endpoint['method'], $endpoint['category'], $endpoint['description'] ?? '',
                ]));
                $score = 0;
                foreach ($terms as $term) {
                    $term = $aliases[$term] ?? $term;
                    if (! str_contains($haystack, $term)) {
                        return null;
                    }
                    $score += str_contains(strtolower($endpoint['name']), $term) ? 3 : 1;
                }

                return $endpoint + ['score' => $score];
            })
            ->filter()
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * Resolve /api-explorer/{category}/{endpoint} to an enabled endpoint.
     */
    public function findBySlug(string $categorySlug, string $endpointSlug): ?Endpoint
    {
        return Endpoint::query()
            ->enabled()
            ->with('category')
            ->whereHas('category', fn ($q) => $q->where('slug', $categorySlug))
            ->get()
            ->first(fn (Endpoint $e) => $e->slug() === $endpointSlug);
    }

    public function find(string $key, bool $includeDisabled = false): ?Endpoint
    {
        $query = Endpoint::query()->with('category')->where('key', $key);

        if (! $includeDisabled) {
            $query->enabled();
        }

        return $query->first();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function summary(Endpoint $endpoint, string $category): array
    {
        return [
            'key' => $endpoint->key,
            'name' => $endpoint->name,
            'method' => $endpoint->method,
            'path' => $endpoint->path,
            'category' => $category,
            // Relative, because this summary is cached across hosts.
            'url' => $endpoint->url(absolute: false),
            'description' => $endpoint->description ? mb_strimwidth($endpoint->description, 0, 160, '…') : null,
            'features' => $endpoint->required_features ?? [],
            'currencies' => $endpoint->supported_currencies,
        ];
    }
}
