<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'description', 'docs_url', 'sort_order', 'is_enabled'])]
class EndpointCategory extends Model
{
    protected $table = 'paystack_endpoint_categories';

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function endpoints(): HasMany
    {
        return $this->hasMany(Endpoint::class, 'category_id');
    }

    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
