<?php

namespace App\Services\Paystack;

use App\Models\Endpoint;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SensitiveDataMasker;
use App\Support\Settings;
use Illuminate\Validation\ValidationException;

/**
 * One generic pipeline executes every endpoint in the registry:
 *
 *   availability → validate → build → live-mode guard → credentials
 *   → send (or mock) → format → log
 */
class EndpointExecutor
{
    public function __construct(
        private readonly EndpointAvailability $availability,
        private readonly EndpointValidator $validator,
        private readonly PaystackRequestBuilder $builder,
        private readonly PaystackCredentials $credentials,
        private readonly PaystackClient $client,
        private readonly MockResponder $mock,
        private readonly PaystackResponseFormatter $formatter,
        private readonly CurlGenerator $curl,
        private readonly RequestLogger $logger,
        private readonly SensitiveDataMasker $masker,
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws ValidationException
     * @throws ExecutionException
     */
    public function execute(
        Endpoint $endpoint,
        array $input,
        ExplorerContext $context,
        ?User $user,
        bool $liveConfirmed = false,
        ?string $ip = null,
    ): array {
        $problems = $this->availability->problems($endpoint, $context);
        if ($problems !== []) {
            throw ExecutionException::unavailable($problems);
        }

        ['values' => $values, 'warnings' => $warnings] = $this->validator->validate($endpoint, $input, $context);

        $request = $this->builder->build($endpoint, $values);

        if ($context->isLive()) {
            $this->guardLive($endpoint, $user, $liveConfirmed);
        }

        $mock = $this->settings->mockMode();

        if ($mock) {
            $response = $this->mock->respond($endpoint, $request);
        } else {
            $key = $this->credentials->keyFor($endpoint->auth, $context->country->code, $context->environment);
            if ($key === null) {
                $status = $this->credentials->status($context->country->code, $context->environment);
                throw ExecutionException::missingCredentials(
                    $context->country->name,
                    $context->environment->label(),
                    $endpoint->auth,
                    $status['env_vars'][$endpoint->auth === 'public' ? 'public' : 'secret'],
                );
            }
            if (! $this->credentials->prefixMatches($key, $endpoint->auth, $context->environment)) {
                throw ExecutionException::keyEnvironmentMismatch($context->environment->label());
            }

            $response = $this->client->send($request, $key);
            unset($key);
        }

        $log = $this->logger->log($endpoint, $context, $user, $values, $request, $response, $ip);

        if ($context->isLive()) {
            $this->audit->record('explorer.live_request', $endpoint, [
                'endpoint' => $endpoint->key,
                'status' => $response->status,
                'log' => $log?->uuid,
            ]);
        }

        $displayBody = $this->masker->mask($request->displayBody());

        return [
            'request' => [
                'method' => $request->method,
                'url' => $request->url(),
                'path' => $request->path,
                'headers' => $request->displayHeaders(),
                'query' => $request->normalisedQuery(),
                'body' => $request->hasBody() ? $displayBody : null,
                'body_type' => $request->hasBody() ? $request->bodyType : 'none',
                'auth' => $request->auth,
            ],
            'response' => $this->formatter->format($response),
            'curl' => $this->curl->generate($request, $displayBody),
            'context' => $context->toPublicArray(),
            'warnings' => $warnings,
            'history_id' => $log?->uuid,
            'mock' => $mock,
        ];
    }

    /**
     * @throws ExecutionException
     */
    private function guardLive(Endpoint $endpoint, ?User $user, bool $liveConfirmed): void
    {
        if (! $this->settings->liveModeAvailable()) {
            throw ExecutionException::liveNotAllowed('Live mode is disabled for this installation.');
        }

        if ($user === null || ! $user->hasPermission('explorer.execute-live')) {
            throw ExecutionException::liveNotAllowed('Your account is not permitted to send live requests.');
        }

        if (! $liveConfirmed) {
            throw ExecutionException::liveNotAllowed(
                $endpoint->is_dangerous
                    ? 'This live request can move real money. Confirm it explicitly to continue.'
                    : 'Live requests must be confirmed explicitly.'
            );
        }
    }
}
