<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records security-relevant actions (logins, role changes, admin edits,
 * live-mode requests). Meta is masked before it is stored.
 */
class AuditLogger
{
    public function __construct(
        private readonly Request $request,
        private readonly SensitiveDataMasker $masker,
    ) {}

    public function record(string $action, ?Model $subject = null, array $meta = [], ?int $userId = null): void
    {
        try {
            AuditLog::create([
                'user_id' => $userId ?? $this->request->user()?->id,
                'action' => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'meta' => $meta ? $this->masker->mask($meta) : null,
                'ip_address' => $this->request->ip(),
                'user_agent' => Str::limit((string) $this->request->userAgent(), 250, ''),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Auditing must never break the request it is auditing.
            Log::warning('Failed to write audit log', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
