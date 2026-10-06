<?php

namespace App\Http\Controllers\Explorer;

use App\Http\Controllers\Controller;
use App\Models\Endpoint;
use App\Services\Paystack\EndpointExecutor;
use App\Services\Paystack\ExecutionException;
use App\Services\Paystack\ExplorerContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Executes any registry endpoint. This single action replaces what would
 * otherwise be one controller method per Paystack operation.
 */
class ExecuteController extends Controller
{
    public function __construct(
        private readonly EndpointExecutor $executor,
        private readonly ExplorerContextResolver $contexts,
    ) {}

    public function __invoke(Request $request, Endpoint $endpoint): JsonResponse
    {
        abort_unless($endpoint->is_enabled && $endpoint->category?->is_enabled, 404);

        $request->validate([
            'params' => ['nullable', 'array'],
            'confirm_live' => ['nullable', 'boolean'],
            'context.country' => ['required', 'string', 'size:2'],
            'context.currency' => ['required', 'string', 'size:3'],
            'context.environment' => ['required', 'string', 'in:test,live'],
        ]);

        $context = $this->contexts->current($request->session(), $request->user());

        // The page the user is looking at must match the server-side context,
        // so a stale tab can never send a request to a different market or
        // to live by surprise.
        $claimed = $request->input('context');
        if ($claimed['country'] !== $context->country->code
            || $claimed['currency'] !== $context->currency->code
            || $claimed['environment'] !== $context->environment->value) {
            return response()->json([
                'message' => 'Your country, currency or environment changed in another tab. Reload the page before executing.',
                'code' => 'context_mismatch',
                'context' => $context->toPublicArray(),
            ], 409);
        }

        $params = array_replace_recursive(
            (array) $request->input('params', []),
            (array) $request->file('params', [])
        );

        try {
            $result = $this->executor->execute(
                $endpoint,
                $params,
                $context,
                $request->user(),
                $request->boolean('confirm_live'),
                $request->ip(),
            );
        } catch (ExecutionException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
                'details' => $e->details,
            ], $e->httpStatus);
        }

        return response()->json($result);
    }
}
