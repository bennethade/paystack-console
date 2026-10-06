<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndpointCategory;
use App\Services\Paystack\EndpointRegistry;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        private readonly EndpointRegistry $registry,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => EndpointCategory::withCount([
                'endpoints',
                'endpoints as enabled_endpoints_count' => fn ($q) => $q->where('is_enabled', true),
            ])->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new EndpointCategory(['is_enabled' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = new EndpointCategory;
        $this->save($request, $category);

        return redirect()->route('admin.categories.index')->with('status', "{$category->name} created.");
    }

    public function edit(EndpointCategory $category): View
    {
        return view('admin.categories.form', ['category' => $category]);
    }

    public function update(Request $request, EndpointCategory $category): RedirectResponse
    {
        $this->save($request, $category);

        return redirect()->route('admin.categories.index')->with('status', "{$category->name} updated.");
    }

    private function save(Request $request, EndpointCategory $category): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('paystack_endpoint_categories', 'slug')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'docs_url' => ['nullable', 'url:https', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $category->fill($data + ['is_enabled' => $request->boolean('is_enabled')])->save();
        $this->registry->flush();
        $this->audit->record('admin.category_saved', $category, ['slug' => $category->slug, 'enabled' => $category->is_enabled]);
    }
}
