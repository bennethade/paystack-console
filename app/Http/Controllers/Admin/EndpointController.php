<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Endpoint;
use App\Models\EndpointCategory;
use App\Models\PaystackCurrency;
use App\Services\Paystack\Data\ParameterDefinition;
use App\Services\Paystack\EndpointRegistry;
use App\Services\Paystack\OpenApi\EndpointImporter;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Manage the endpoint registry without touching code: enable/disable,
 * edit documentation and parameters, add endpoints, re-import the spec.
 */
class EndpointController extends Controller
{
    public function __construct(
        private readonly EndpointRegistry $registry,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'state' => ['nullable', 'in:enabled,disabled,modified,undocumented,manual'],
        ]);

        $endpoints = Endpoint::with('category')
            ->when($filters['q'] ?? null, function ($q, $v) {
                $like = '%'.addcslashes($v, '%_').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('path', 'like', $like)->orWhere('key', 'like', $like));
            })
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when(($filters['state'] ?? null) === 'enabled', fn ($q) => $q->where('is_enabled', true))
            ->when(($filters['state'] ?? null) === 'disabled', fn ($q) => $q->where('is_enabled', false))
            ->when(($filters['state'] ?? null) === 'modified', fn ($q) => $q->where('is_locally_modified', true))
            ->when(($filters['state'] ?? null) === 'undocumented', fn ($q) => $q->where('is_documented', false))
            ->when(($filters['state'] ?? null) === 'manual', fn ($q) => $q->where('source', 'manual'))
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->paginate(50)
            ->withQueryString();

        return view('admin.endpoints.index', [
            'endpoints' => $endpoints,
            'categories' => EndpointCategory::ordered()->get(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.endpoints.form', $this->formData(new Endpoint([
            'method' => 'GET',
            'auth' => 'secret',
            'body_type' => 'none',
            'is_enabled' => false,
            'is_documented' => true,
            'parameters' => [],
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $endpoint = new Endpoint(['source' => 'manual']);
        $this->save($request, $endpoint);
        $this->audit->record('admin.endpoint_created', $endpoint, ['key' => $endpoint->key]);

        return redirect()->route('admin.endpoints.edit', $endpoint)->with('status', 'Endpoint created.');
    }

    public function edit(Endpoint $endpoint): View
    {
        return view('admin.endpoints.form', $this->formData($endpoint));
    }

    public function update(Request $request, Endpoint $endpoint): RedirectResponse
    {
        $this->save($request, $endpoint);
        $this->audit->record('admin.endpoint_updated', $endpoint, ['key' => $endpoint->key, 'enabled' => $endpoint->is_enabled]);

        return redirect()->route('admin.endpoints.edit', $endpoint)->with('status', 'Endpoint saved.');
    }

    public function toggle(Endpoint $endpoint): RedirectResponse
    {
        $endpoint->update(['is_enabled' => ! $endpoint->is_enabled]);
        $this->registry->flush();
        $this->audit->record('admin.endpoint_toggled', $endpoint, ['key' => $endpoint->key, 'enabled' => $endpoint->is_enabled]);

        return back()->with('status', $endpoint->name.($endpoint->is_enabled ? ' enabled.' : ' disabled.'));
    }

    public function destroy(Endpoint $endpoint): RedirectResponse
    {
        abort_unless($endpoint->source === 'manual', 403, 'Imported endpoints can be disabled but not deleted (a re-import would recreate them).');

        $this->audit->record('admin.endpoint_deleted', $endpoint, ['key' => $endpoint->key]);
        $endpoint->delete();
        $this->registry->flush();

        return redirect()->route('admin.endpoints.index')->with('status', 'Endpoint deleted.');
    }

    /**
     * Re-run the OpenAPI importer against the bundled spec.
     */
    public function import(Request $request, EndpointImporter $importer): RedirectResponse
    {
        try {
            $stats = $importer->import(config('paystack.openapi.spec_path'), $request->boolean('force'));
        } catch (Throwable $e) {
            return back()->withErrors(['import' => 'Import failed: '.$e->getMessage()]);
        }

        $this->audit->record('admin.endpoints_imported', meta: $stats + ['force' => $request->boolean('force')]);

        return back()->with('status', sprintf(
            'Import complete: %d created, %d updated, %d locally modified endpoints preserved.',
            $stats['created'], $stats['updated'], $stats['preserved']
        ));
    }

    private function save(Request $request, Endpoint $endpoint): void
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_]+(\.[a-z0-9_]+)*$/', Rule::unique('paystack_endpoints', 'key')->ignore($endpoint->id)],
            'category_id' => ['required', 'integer', 'exists:paystack_endpoint_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'method' => ['required', Rule::in(Endpoint::METHODS)],
            'path' => ['required', 'string', 'max:255', 'regex:#^/[A-Za-z0-9_\-/{}.]*$#'],
            'auth' => ['required', Rule::in(['secret', 'public'])],
            'body_type' => ['required', Rule::in(['json', 'multipart', 'none'])],
            'workflow' => ['nullable', Rule::in(array_keys(Endpoint::WORKFLOWS))],
            'pagination' => ['nullable', Rule::in(['page', 'cursor', 'both'])],
            'docs_url' => ['nullable', 'url:https', 'max:255'],
            'required_features' => ['nullable', 'array'],
            'required_features.*' => [Rule::in(array_keys(config('paystack.feature_labels')))],
            'supported_currencies' => ['nullable', 'array'],
            'supported_currencies.*' => ['string', 'exists:paystack_currencies,code'],
            'parameters_json' => ['nullable', 'string', 'max:200000'],
        ]);

        $parameters = $this->parseParameters($data['parameters_json'] ?? '[]', $data['path']);

        $endpoint->fill([
            'key' => $data['key'],
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'method' => $data['method'],
            'path' => $data['path'],
            'auth' => $data['auth'],
            'body_type' => $data['method'] === 'GET' ? 'none' : $data['body_type'],
            'workflow' => $data['workflow'] ?? null,
            'pagination' => $data['pagination'] ?? null,
            'docs_url' => $data['docs_url'] ?? null,
            'required_features' => $data['required_features'] ?? null,
            'supported_currencies' => $data['supported_currencies'] ?? null,
            'parameters' => $parameters,
            'is_enabled' => $request->boolean('is_enabled'),
            'is_dangerous' => $request->boolean('is_dangerous'),
            'is_documented' => $request->boolean('is_documented'),
        ]);

        if ($endpoint->exists && $endpoint->source === 'openapi') {
            $endpoint->is_locally_modified = true;
        }

        $endpoint->save();
        $this->registry->flush();
    }

    /**
     * @throws ValidationException
     */
    private function parseParameters(string $json, string $path): array
    {
        $decoded = json_decode($json === '' ? '[]' : $json, true);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw ValidationException::withMessages(['parameters_json' => 'Parameters must be a JSON array of parameter objects.']);
        }

        $parameters = [];
        $names = [];
        foreach ($decoded as $index => $parameter) {
            if (! is_array($parameter) || ! isset($parameter['name']) || ! is_string($parameter['name'])
                || ! preg_match('/^[A-Za-z0-9_\-]+$/', $parameter['name'])) {
                throw ValidationException::withMessages(['parameters_json' => "Parameter #{$index} needs a valid \"name\"."]);
            }
            if (! in_array($parameter['in'] ?? 'body', ParameterDefinition::LOCATIONS, true)) {
                throw ValidationException::withMessages(['parameters_json' => "Parameter \"{$parameter['name']}\" has an invalid \"in\" (path, query or body)."]);
            }
            if (! in_array($parameter['type'] ?? 'string', ParameterDefinition::TYPES, true)) {
                throw ValidationException::withMessages(['parameters_json' => "Parameter \"{$parameter['name']}\" has an invalid \"type\"."]);
            }

            $parameters[] = ParameterDefinition::fromArray($parameter)->toArray();
            $names[] = $parameter['in'] === 'path' ? $parameter['name'] : null;
        }

        preg_match_all('/\{([^}]+)\}/', $path, $placeholders);
        foreach ($placeholders[1] as $placeholder) {
            if (! in_array($placeholder, $names, true)) {
                throw ValidationException::withMessages(['parameters_json' => "Path placeholder {{$placeholder}} needs a parameter with \"in\": \"path\"."]);
            }
        }

        return $parameters;
    }

    private function formData(Endpoint $endpoint): array
    {
        return [
            'endpoint' => $endpoint,
            'categories' => EndpointCategory::ordered()->get(),
            'currencies' => PaystackCurrency::orderBy('code')->pluck('code'),
            'features' => config('paystack.feature_labels'),
            'parametersJson' => json_encode($endpoint->parameters ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }
}
