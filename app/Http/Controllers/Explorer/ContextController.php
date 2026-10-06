<?php

namespace App\Http\Controllers\Explorer;

use App\Http\Controllers\Controller;
use App\Services\Paystack\ExplorerContextResolver;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Country / currency / environment switcher.
 */
class ContextController extends Controller
{
    public function __construct(
        private readonly ExplorerContextResolver $contexts,
        private readonly AuditLogger $audit,
    ) {}

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['nullable', 'string', 'size:3'],
            'environment' => ['required', 'string', 'in:test,live'],
        ]);

        $before = $this->contexts->current($request->session(), $request->user());

        $context = $this->contexts->update(
            $request->session(),
            $request->user(),
            strtoupper($data['country']),
            isset($data['currency']) ? strtoupper($data['currency']) : null,
            $data['environment'],
        );

        if ($context->isLive() && ! $before->isLive()) {
            $this->audit->record('explorer.switched_to_live', meta: $context->toPublicArray());
        }

        if ($request->expectsJson()) {
            return response()->json(['context' => $context->toPublicArray()]);
        }

        return back()->with('status', "Now using {$context->country->name} / {$context->currency->code} ({$context->environment->label()}).");
    }
}
