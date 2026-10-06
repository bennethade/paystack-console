<?php

namespace App\Http\Controllers\Explorer;

use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use App\Models\SavedRequest;
use App\Services\Paystack\CurlGenerator;
use App\Services\Paystack\Data\PaystackRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in user's own request history. Admins see global activity in
 * the admin area instead.
 */
class HistoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'endpoint' => ['nullable', 'string', 'max:100'],
            'outcome' => ['nullable', 'in:success,error'],
            'environment' => ['nullable', 'in:test,live'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $logs = ApiRequestLog::forUser($request->user())
            ->when($filters['endpoint'] ?? null, fn ($q, $v) => $q->where('endpoint_key', 'like', '%'.addcslashes($v, '%_').'%'))
            ->when($filters['outcome'] ?? null, fn ($q, $v) => $q->where('success', $v === 'success'))
            ->when($filters['environment'] ?? null, fn ($q, $v) => $q->where('environment', $v))
            ->when($filters['country'] ?? null, fn ($q, $v) => $q->where('country_code', strtoupper($v)))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('explorer.history.index', ['logs' => $logs, 'filters' => $filters]);
    }

    public function show(ApiRequestLog $log, CurlGenerator $curl): View
    {
        $this->authorize('view', $log);
        $log->load('endpoint.category', 'user');

        return view('explorer.history.show', [
            'log' => $log,
            'curl' => $curl->generate($this->rebuild($log), $log->request_body),
        ]);
    }

    /**
     * Load the request into the form. Never executes it.
     */
    public function replay(ApiRequestLog $log): RedirectResponse
    {
        $this->authorize('view', $log);

        $endpoint = $log->endpoint;
        if (! $endpoint || ! $endpoint->is_enabled) {
            return back()->withErrors(['history' => 'That endpoint is no longer available.']);
        }

        return redirect($endpoint->url(['replay' => $log->uuid]))
            ->with('status', 'Request loaded into the form. Review it, then execute when ready.');
    }

    /**
     * Copy a history entry into a named saved configuration.
     */
    public function duplicate(Request $request, ApiRequestLog $log): RedirectResponse
    {
        $this->authorize('view', $log);

        if (! $log->endpoint_id) {
            return back()->withErrors(['history' => 'That endpoint is no longer available.']);
        }

        $saved = SavedRequest::create([
            'user_id' => $request->user()->id,
            'endpoint_id' => $log->endpoint_id,
            'name' => 'Copy of '.$log->endpoint_key.' ('.$log->created_at->format('M j, H:i').')',
            'country_code' => $log->country_code,
            'currency_code' => $log->currency_code,
            'params' => $log->params,
        ]);

        return redirect($log->endpoint->url(['saved' => $saved->id]))
            ->with('status', 'Request duplicated into a saved configuration.');
    }

    public function destroy(ApiRequestLog $log): RedirectResponse
    {
        $this->authorize('delete', $log);
        $log->delete();

        return redirect()->route('explorer.history.index')->with('status', 'History entry deleted.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $count = ApiRequestLog::forUser($request->user())->delete();

        return redirect()->route('explorer.history.index')->with('status', "Cleared {$count} history entries.");
    }

    private function rebuild(ApiRequestLog $log): PaystackRequest
    {
        return new PaystackRequest(
            method: $log->method,
            baseUrl: config('paystack.base_url'),
            path: $log->path,
            query: $log->request_query ?? [],
            body: $log->request_body ?? [],
            bodyType: $log->request_body ? 'json' : 'none',
            auth: $log->endpoint?->auth ?? 'secret',
        );
    }
}
