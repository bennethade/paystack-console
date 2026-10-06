<?php

namespace App\Http\Controllers\Explorer;

use App\Http\Controllers\Controller;
use App\Models\Endpoint;
use App\Models\SavedRequest;
use App\Services\Paystack\ExplorerContextResolver;
use App\Support\SensitiveDataMasker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Named, reusable request configurations per user.
 */
class SavedRequestController extends Controller
{
    public function index(Request $request): View
    {
        return view('explorer.saved.index', [
            'saved' => SavedRequest::where('user_id', $request->user()->id)
                ->with('endpoint.category')
                ->latest()
                ->paginate(25),
        ]);
    }

    public function store(Request $request, Endpoint $endpoint, ExplorerContextResolver $contexts, SensitiveDataMasker $masker): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'params' => ['nullable', 'array'],
        ]);

        $names = array_column($endpoint->parameters ?? [], 'name');
        $params = array_intersect_key($data['params'] ?? [], array_flip($names));
        $context = $contexts->current($request->session(), $request->user());

        $saved = SavedRequest::create([
            'user_id' => $request->user()->id,
            'endpoint_id' => $endpoint->id,
            'name' => $data['name'],
            'country_code' => $context->country->code,
            'currency_code' => $context->currency->code,
            // Secrets such as PIN/OTP are never persisted.
            'params' => $masker->mask($params),
        ]);

        return response()->json([
            'id' => $saved->id,
            'name' => $saved->name,
            'url' => $endpoint->url(['saved' => $saved->id]),
        ], 201);
    }

    public function destroy(SavedRequest $saved): RedirectResponse
    {
        $this->authorize('delete', $saved);
        $saved->delete();

        return back()->with('status', 'Saved configuration deleted.');
    }
}
