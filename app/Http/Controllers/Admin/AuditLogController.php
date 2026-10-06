<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $action = $request->validate(['action' => ['nullable', 'string', 'max:100']])['action'] ?? null;

        return view('admin.audit.index', [
            'entries' => AuditLog::with('user')
                ->when($action, fn ($q) => $q->where('action', 'like', addcslashes($action, '%_').'%'))
                ->latest('created_at')
                ->paginate(50)
                ->withQueryString(),
            'action' => $action,
        ]);
    }
}
