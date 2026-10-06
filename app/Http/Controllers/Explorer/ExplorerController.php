<?php

namespace App\Http\Controllers\Explorer;

use App\Enums\PaystackEnvironment;
use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use App\Models\Endpoint;
use App\Models\SavedRequest;
use App\Services\Paystack\EndpointAvailability;
use App\Services\Paystack\EndpointRegistry;
use App\Services\Paystack\EndpointValidator;
use App\Services\Paystack\ExplorerContext;
use App\Services\Paystack\ExplorerContextResolver;
use App\Services\Paystack\PaystackCountryResolver;
use App\Services\Paystack\PaystackCredentials;
use App\Support\SensitiveDataMasker;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExplorerController extends Controller
{
    /**
     * Endpoint that confirms the outcome of a request returning a reference.
     */
    private const VERIFY_WITH = [
        'transaction.initialize' => 'transaction.verify',
        'transaction.charge_authorization' => 'transaction.verify',
        'transaction.partial_debit' => 'transaction.verify',
        'charge.create' => 'charge.check',
        'charge.submit_pin' => 'charge.check',
        'charge.submit_otp' => 'charge.check',
        'customer.initialize_authorization' => 'customer.verify_authorization',
        'preauthorization.initialize' => 'preauthorization.verify',
        'preauthorization.reserve_authorization' => 'preauthorization.verify',
        'transfer.initiate' => 'transfer.verify',
    ];

    public function __construct(
        private readonly EndpointRegistry $registry,
        private readonly ExplorerContextResolver $contexts,
        private readonly EndpointAvailability $availability,
        private readonly EndpointValidator $validator,
        private readonly PaystackCountryResolver $countries,
        private readonly PaystackCredentials $credentials,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $context = $this->contexts->current($request->session(), $user);

        $credentialMatrix = [];
        foreach ($this->countries->enabled() as $country) {
            foreach (PaystackEnvironment::cases() as $env) {
                $credentialMatrix[$country->code][$env->value] = $this->credentials->status($country->code, $env);
            }
        }

        return view('explorer.index', [
            'context' => $context,
            'navigation' => $this->registry->navigation(),
            'recent' => ApiRequestLog::forUser($user)->latest()->limit(8)->get(),
            'favorites' => $user->favoriteEndpoints()->with('category')->where('is_enabled', true)->get(),
            'endpointCount' => collect($this->registry->navigation())->sum(fn ($c) => count($c['endpoints'])),
            'categoryCount' => $this->registry->navigation()->count(),
            'credentialMatrix' => $credentialMatrix,
            'countries' => $this->countries->enabled(),
        ]);
    }

    public function show(Request $request, string $category, string $endpoint): View
    {
        $model = $this->registry->findBySlug($category, $endpoint);
        abort_if($model === null, 404);

        $user = $request->user();
        $context = $this->contexts->current($request->session(), $user);

        [$prefill, $prefillSource, $maskedFields] = $this->prefill($request, $model);

        return view('explorer.endpoint', [
            'endpoint' => $model,
            'context' => $context,
            'problems' => $this->availability->problems($model, $context),
            'formConfig' => $this->formConfig($model, $context),
            'prefill' => $prefill,
            'prefillSource' => $prefillSource,
            'maskedFields' => $maskedFields,
            'isFavorite' => $user->favoriteEndpoints()->whereKey($model->id)->exists(),
            'savedRequests' => SavedRequest::where('user_id', $user->id)->where('endpoint_id', $model->id)->latest()->get(),
            'recentRuns' => ApiRequestLog::forUser($user)->where('endpoint_id', $model->id)->latest()->limit(5)->get(),
        ]);
    }

    /**
     * Everything the request form needs to bind parameters to the market:
     * narrowed enums, defaults and currency metadata. No credentials.
     */
    private function formConfig(Endpoint $endpoint, ExplorerContext $context): array
    {
        $allowed = [];
        $defaults = [];

        foreach ($endpoint->parameterDefinitions() as $parameter) {
            $values = $this->validator->allowedValues($parameter, $context);
            if ($values !== null) {
                $allowed[$parameter->name] = $values;
            }

            $defaults[$parameter->name] = match ($parameter->binding) {
                'currency' => in_array($context->currency->code, $values ?? [$context->currency->code], true) ? $context->currency->code : null,
                'country_code' => in_array($context->country->code, $values ?? [$context->country->code], true) ? $context->country->code : null,
                'bank_country' => $context->country->bank_country,
                'callback_url' => route('explorer.callback'),
                default => $parameter->default,
            };
        }

        $channelLabels = collect(config('paystack.channels'))->map(fn ($c) => $c['label'])->all();

        $verifyEndpoint = isset(self::VERIFY_WITH[$endpoint->key]) ? $this->registry->find(self::VERIFY_WITH[$endpoint->key]) : null;

        return [
            'verify_url' => $verifyEndpoint?->url(['prefill' => ['reference' => '']]),
            'verify_name' => $verifyEndpoint?->name,
            'allowed' => $allowed,
            'defaults' => array_filter($defaults, fn ($v) => $v !== null),
            'channel_labels' => $channelLabels,
            'currency' => $context->currency->toPublicArray(),
            'currencies' => $context->country->currencies->where('is_enabled', true)->map->toPublicArray()->values()->all(),
            'context' => $context->toPublicArray(),
            'execute_url' => route('explorer.execute', $endpoint),
            'save_url' => route('explorer.saved.store', $endpoint),
            'favorite_url' => route('explorer.favorites.toggle', $endpoint),
            'base_url' => config('paystack.base_url'),
            'max_upload_kb' => (int) config('paystack.uploads.max_kb'),
        ];
    }

    /**
     * Replay (?replay=uuid), saved configuration (?saved=id) or explicit
     * values (?prefill[reference]=...). Never auto-executes.
     *
     * @return array{0: array, 1: ?string, 2: list<string>}
     */
    private function prefill(Request $request, Endpoint $endpoint): array
    {
        $user = $request->user();
        $params = [];
        $source = null;

        if ($uuid = $request->query('replay')) {
            $log = ApiRequestLog::where('uuid', $uuid)->where('endpoint_id', $endpoint->id)->first();
            if ($log && $user->can('view', $log)) {
                $params = $log->params ?? [];
                $source = 'Replaying request from '.$log->created_at->format('Y-m-d H:i:s').' ('.strtoupper($log->environment).', '.$log->country_code.'/'.$log->currency_code.')';
            }
        } elseif ($savedId = $request->query('saved')) {
            $saved = SavedRequest::whereKey($savedId)->where('endpoint_id', $endpoint->id)->first();
            if ($saved && $user->can('view', $saved)) {
                $params = $saved->params ?? [];
                $source = "Loaded saved configuration \"{$saved->name}\"";
            }
        } elseif (is_array($request->query('prefill'))) {
            $names = array_column($endpoint->parameters ?? [], 'name');
            $params = array_intersect_key($request->query('prefill'), array_flip($names));
            $source = $params ? 'Pre-filled from link' : null;
        }

        // Masked values (PIN, OTP...) cannot be replayed; the user re-enters them.
        $masked = [];
        foreach ($params as $name => $value) {
            if ($value === SensitiveDataMasker::MASK) {
                $masked[] = $name;
                unset($params[$name]);
            }
        }

        return [$params, $source, $masked];
    }
}
