<?php

namespace App\Models;

use App\Services\Paystack\Data\ParameterDefinition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A single Paystack API operation in the endpoint registry.
 *
 * @property array<int, array<string, mixed>>|null $parameters
 */
#[Fillable([
    'key', 'operation_id', 'category_id', 'name', 'description', 'method', 'path', 'auth', 'body_type',
    'parameters', 'example', 'workflow', 'pagination', 'required_features', 'supported_currencies',
    'is_dangerous', 'is_documented', 'notes', 'docs_url', 'source', 'is_locally_modified', 'is_enabled', 'sort_order',
])]
class Endpoint extends Model
{
    protected $table = 'paystack_endpoints';

    public const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public const WORKFLOWS = [
        'payment_redirect' => 'Payment redirect (opens checkout / authorization URL)',
        'bank_table' => 'Searchable bank table',
        'transaction_result' => 'Transaction result summary',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'example' => 'array',
            'required_features' => 'array',
            'supported_currencies' => 'array',
            'is_dangerous' => 'boolean',
            'is_documented' => 'boolean',
            'is_locally_modified' => 'boolean',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * URL segment within the category: transaction.initialize → initialize.
     */
    public function slug(): string
    {
        $name = str_contains($this->key, '.') ? substr($this->key, strpos($this->key, '.') + 1) : $this->key;

        return Str::slug(str_replace(['_', '.'], '-', $name));
    }

    /**
     * Explorer page URL, e.g. /api-explorer/transactions/initialize.
     */
    public function url(array $query = [], bool $absolute = true): string
    {
        return route('explorer.endpoint', ['category' => $this->category->slug, 'endpoint' => $this->slug()] + $query, $absolute);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EndpointCategory::class, 'category_id');
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_favorites')->withTimestamps();
    }

    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true)
            ->whereHas('category', fn (Builder $q) => $q->where('is_enabled', true));
    }

    /**
     * @return list<ParameterDefinition>
     */
    public function parameterDefinitions(): array
    {
        return array_map(
            fn (array $p) => ParameterDefinition::fromArray($p),
            $this->parameters ?? []
        );
    }

    public function sendsBody(): bool
    {
        return $this->method !== 'GET' && $this->body_type !== 'none';
    }

    public function hasFileParameters(): bool
    {
        foreach ($this->parameterDefinitions() as $parameter) {
            if ($parameter->type === 'file') {
                return true;
            }
        }

        return false;
    }

    /**
     * Endpoint definition shipped to the browser to build the request form.
     * Contains only documentation data.
     */
    public function toClientArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'method' => $this->method,
            'path' => $this->path,
            'auth' => $this->auth,
            'body_type' => $this->body_type,
            'workflow' => $this->workflow,
            'pagination' => $this->pagination,
            'is_dangerous' => $this->is_dangerous,
            'docs_url' => $this->docs_url,
            'category' => $this->category?->name,
            'parameters' => $this->parameters ?? [],
            'example' => $this->example,
            'required_features' => $this->required_features ?? [],
            'supported_currencies' => $this->supported_currencies,
        ];
    }
}
