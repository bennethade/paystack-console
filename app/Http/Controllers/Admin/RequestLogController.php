<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global request activity across all users.
 */
class RequestLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'user' => ['nullable', 'integer'],
            'endpoint' => ['nullable', 'string', 'max:100'],
            'outcome' => ['nullable', 'in:success,error'],
            'environment' => ['nullable', 'in:test,live'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        return view('admin.logs.index', [
            'logs' => ApiRequestLog::with('user')
                ->when($filters['user'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
                ->when($filters['endpoint'] ?? null, fn ($q, $v) => $q->where('endpoint_key', 'like', '%'.addcslashes($v, '%_').'%'))
                ->when($filters['outcome'] ?? null, fn ($q, $v) => $q->where('success', $v === 'success'))
                ->when($filters['environment'] ?? null, fn ($q, $v) => $q->where('environment', $v))
                ->when($filters['country'] ?? null, fn ($q, $v) => $q->where('country_code', strtoupper($v)))
                ->latest()
                ->paginate(50)
                ->withQueryString(),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'filters' => $filters,
        ]);
    }

    public function purge(Request $request, AuditLogger $audit): RedirectResponse
    {
        $days = (int) $request->validate(['days' => ['required', 'integer', 'min:0', 'max:3650']])['days'];

        $count = ApiRequestLog::where('created_at', '<', now()->subDays($days))->delete();
        $audit->record('admin.logs_purged', meta: ['older_than_days' => $days, 'deleted' => $count]);

        return back()->with('status', "Deleted {$count} request log entries older than {$days} days.");
    }
}
