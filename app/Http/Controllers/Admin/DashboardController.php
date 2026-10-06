<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use App\Models\AuditLog;
use App\Models\Endpoint;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $since = now()->subDay();

        $last24h = ApiRequestLog::where('created_at', '>=', $since);

        return view('admin.dashboard', [
            'stats' => [
                'users' => User::count(),
                'endpoints_enabled' => Endpoint::where('is_enabled', true)->count(),
                'endpoints_total' => Endpoint::count(),
                'requests_24h' => (clone $last24h)->count(),
                'errors_24h' => (clone $last24h)->where('success', false)->count(),
                'live_24h' => (clone $last24h)->where('environment', 'live')->count(),
                'avg_ms_24h' => (int) (clone $last24h)->avg('duration_ms'),
                'webhooks_24h' => WebhookEvent::where('created_at', '>=', $since)->count(),
            ],
            'topEndpoints' => ApiRequestLog::where('created_at', '>=', now()->subDays(7))
                ->selectRaw('endpoint_key, count(*) as total, sum(case when success then 0 else 1 end) as failures')
                ->groupBy('endpoint_key')
                ->orderByDesc('total')
                ->limit(8)
                ->get(),
            'recentRequests' => ApiRequestLog::with('user')->latest()->limit(10)->get(),
            'recentAudit' => AuditLog::with('user')->latest('created_at')->limit(10)->get(),
        ]);
    }
}
