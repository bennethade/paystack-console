<?php

namespace App\Http\Controllers\Explorer;

use App\Http\Controllers\Controller;
use App\Services\Paystack\EndpointExecutor;
use App\Services\Paystack\EndpointRegistry;
use App\Services\Paystack\ExecutionException;
use App\Services\Paystack\ExplorerContextResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Default callback_url for payment flows. Paystack redirects here after
 * checkout with ?reference=...&trxref=...
 *
 * In test mode the transaction is verified automatically (a read-only GET).
 * In live mode verification is offered as a button and is never automatic.
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        EndpointRegistry $registry,
        ExplorerContextResolver $contexts,
        EndpointExecutor $executor,
    ): View {
        $reference = (string) ($request->query('reference') ?: $request->query('trxref', ''));
        $reference = preg_match('/^[A-Za-z0-9\-_.=]{1,100}$/', $reference) ? $reference : '';

        $context = $contexts->current($request->session(), $request->user());
        $verifyEndpoint = $registry->find('transaction.verify');

        $result = null;
        $error = null;

        $canVerify = $request->user()->can('explorer.execute');

        if ($reference !== '' && $verifyEndpoint && $canVerify && ! $context->isLive() && ! $request->boolean('mock')) {
            try {
                $result = $executor->execute(
                    $verifyEndpoint,
                    ['reference' => $reference],
                    $context,
                    $request->user(),
                    false,
                    $request->ip(),
                );
            } catch (ExecutionException $e) {
                $error = $e->getMessage();
            } catch (ValidationException $e) {
                $error = collect($e->errors())->flatten()->first();
            }
        }

        $data = $result['response']['body']['data'] ?? null;

        return view('explorer.callback', [
            'reference' => $reference,
            'context' => $context,
            'isMock' => $request->boolean('mock'),
            'result' => $result,
            'transaction' => is_array($data) ? $data : null,
            'error' => $error,
            'verifyUrl' => $verifyEndpoint && $reference !== ''
                ? $verifyEndpoint->url(['prefill' => ['reference' => $reference]])
                : null,
        ]);
    }
}
