<?php

namespace App\Services\Paystack;

use App\Models\Endpoint;
use App\Services\Paystack\Data\ParameterDefinition;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates explorer input against an endpoint's parameter definitions and
 * the selected market, then casts values to the types Paystack expects.
 *
 * Only parameters defined for the endpoint are passed through; anything else
 * in the input is discarded.
 */
class EndpointValidator
{
    private const AMOUNT_FIELDS = ['amount', 'price', 'refund_amount'];

    /**
     * @param  array<string, mixed>  $input
     * @return array{values: array<string, mixed>, warnings: list<string>}
     *
     * @throws ValidationException
     */
    public function validate(Endpoint $endpoint, array $input, ExplorerContext $context): array
    {
        $definitions = $endpoint->parameterDefinitions();
        [$data, $jsonErrors] = $this->normalise($definitions, $input);

        $rules = [];
        $attributes = [];
        foreach ($definitions as $parameter) {
            $field = 'params.'.$parameter->name;
            $rules[$field] = $this->rulesFor($parameter, $context);
            $attributes[$field] = $parameter->name;

            if ($parameter->type === 'array') {
                $itemRules = $this->itemRulesFor($parameter, $context);
                if ($itemRules) {
                    $rules[$field.'.*'] = $itemRules;
                    $attributes[$field.'.*'] = $parameter->name.' item';
                }

                // Arrays of objects (JSON editor): every item must be an object
                // carrying the documented required fields.
                if (($parameter->items['type'] ?? null) === 'object') {
                    $rules[$field.'.*'] = ['array'];
                    $attributes[$field.'.*'] = "{$parameter->name} item :position";

                    foreach ($parameter->items['properties'] ?? [] as $property) {
                        $propertyRules = $this->itemPropertyRules($property);
                        if ($propertyRules) {
                            $rules[$field.'.*.'.$property['name']] = $propertyRules;
                            $attributes[$field.'.*.'.$property['name']] = "item :position: {$property['name']}";
                        }
                    }
                }
            }
        }

        $validator = Validator::make(['params' => $data], $rules, $this->messages($context), $attributes);

        $validator->after(function ($validator) use ($jsonErrors) {
            foreach ($jsonErrors as $name => $message) {
                $validator->errors()->add('params.'.$name, $message);
            }
        });

        $validated = $validator->validate()['params'] ?? [];

        $values = [];
        foreach ($definitions as $parameter) {
            if (array_key_exists($parameter->name, $validated) && $validated[$parameter->name] !== null) {
                $values[$parameter->name] = $this->cast($parameter, $validated[$parameter->name]);
            }
        }

        return ['values' => $values, 'warnings' => $this->warnings($endpoint, $values, $context)];
    }

    /**
     * Drop empty values and decode JSON-edited fields.
     *
     * @param  list<ParameterDefinition>  $definitions
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function normalise(array $definitions, array $input): array
    {
        $data = [];
        $errors = [];

        foreach ($definitions as $parameter) {
            if (! array_key_exists($parameter->name, $input)) {
                continue;
            }

            $value = $input[$parameter->name];

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if ($parameter->type === 'file') {
                $data[$parameter->name] = $value;

                continue;
            }

            if ($parameter->type === 'boolean' && is_string($value)) {
                $value = match (strtolower($value)) {
                    'true', '1', 'on', 'yes' => true,
                    'false', '0', 'off', 'no' => false,
                    default => $value,
                };
            }

            $wantsStructure = $parameter->type === 'object' || ($parameter->type === 'array' && $parameter->ui() === 'json');
            if ($wantsStructure && is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[$parameter->name] = ucfirst($parameter->name).' must be valid JSON ('.json_last_error_msg().').';

                    continue;
                }
                $value = $decoded;
            }

            // "Stringified JSON" string fields: accept an object and stringify it.
            if ($parameter->type === 'string' && $parameter->isJsonLike()) {
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                } elseif (is_string($value)) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors[$parameter->name] = ucfirst($parameter->name).' must be valid JSON ('.json_last_error_msg().').';

                        continue;
                    }
                }
            }

            if ($parameter->type === 'array' && is_string($value)) {
                $value = array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
            }

            if ($parameter->type === 'array' && is_array($value) && $parameter->ui() !== 'json') {
                $value = array_values(array_filter($value, fn ($v) => $v !== null && $v !== ''));
                if ($value === []) {
                    continue;
                }
            }

            $data[$parameter->name] = $value;
        }

        return [$data, $errors];
    }

    private function rulesFor(ParameterDefinition $parameter, ExplorerContext $context): array
    {
        $rules = [$parameter->required ? 'required' : 'nullable'];

        switch ($parameter->type) {
            case 'integer':
                $rules[] = 'integer';
                if (in_array($parameter->name, self::AMOUNT_FIELDS, true)) {
                    $rules[] = 'min:1';
                }
                break;
            case 'number':
                $rules[] = 'numeric';
                break;
            case 'boolean':
                $rules[] = 'boolean';
                break;
            case 'array':
                $rules[] = 'array';
                $rules[] = 'max:1000';
                break;
            case 'object':
                $rules[] = 'array';
                break;
            case 'file':
                $rules[] = 'file';
                $rules[] = 'max:'.(int) config('paystack.uploads.max_kb', 5120);
                break;
            default:
                $rules[] = 'string';
                $rules[] = $parameter->isJsonLike() ? 'max:65535' : 'max:5000';
                if ($parameter->in === 'path') {
                    $rules[] = 'max:255';
                }
                if (in_array($parameter->name, self::AMOUNT_FIELDS, true)) {
                    $rules[] = 'regex:/^[1-9][0-9]*$/';
                }
        }

        if ($parameter->type === 'string') {
            match ($parameter->format) {
                'email' => $rules[] = 'email',
                'uri' => $rules[] = 'url',
                'date', 'date-time' => $rules[] = 'date',
                default => null,
            };
        }

        $allowed = $this->allowedValues($parameter, $context);
        if ($allowed !== null && $parameter->type !== 'array') {
            $rules[] = Rule::in($allowed);
        }

        return $rules;
    }

    private function itemRulesFor(ParameterDefinition $parameter, ExplorerContext $context): array
    {
        if ($parameter->ui() === 'json') {
            return [];
        }

        $rules = [match ($parameter->items['type'] ?? 'string') {
            'integer' => 'integer',
            'number' => 'numeric',
            default => 'string',
        }];

        $allowed = $this->allowedValues($parameter, $context);
        if ($allowed !== null) {
            $rules[] = Rule::in($allowed);
        }

        return $rules;
    }

    /**
     * Rules for one documented property of an object inside an array.
     */
    private function itemPropertyRules(array $property): array
    {
        $rules = [! empty($property['required']) ? 'required' : 'nullable'];

        $typeRule = match ($property['type'] ?? null) {
            'integer' => 'integer',
            'number' => 'numeric',
            'boolean' => 'boolean',
            'string' => 'string',
            'object', 'array' => 'array',
            default => null,
        };
        if ($typeRule) {
            $rules[] = $typeRule;
        }
        if (in_array($property['name'], self::AMOUNT_FIELDS, true) && ($property['type'] ?? null) === 'integer') {
            $rules[] = 'min:1';
        }
        if (! empty($property['enum'])) {
            $rules[] = Rule::in($property['enum']);
        }

        return count($rules) > 1 || $rules[0] === 'required' ? $rules : [];
    }

    /**
     * Enum values narrowed by the context binding. Null = unrestricted.
     */
    public function allowedValues(ParameterDefinition $parameter, ExplorerContext $context): ?array
    {
        $enum = $parameter->type === 'array' ? ($parameter->items['enum'] ?? null) : $parameter->enum;

        $contextual = match ($parameter->binding) {
            'currency' => $context->country->currencies->where('is_enabled', true)->pluck('code')->all(),
            'channels' => $context->country->channels ?? [],
            'recipient_type' => $context->country->recipient_types ?? [],
            default => null,
        };

        if ($contextual === null) {
            return $enum;
        }

        return $enum === null ? array_values($contextual) : array_values(array_intersect($enum, $contextual));
    }

    private function cast(ParameterDefinition $parameter, mixed $value): mixed
    {
        return match ($parameter->type) {
            'integer' => (int) $value,
            'number' => is_numeric($value) && str_contains((string) $value, '.') ? (float) $value : (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => $parameter->ui() === 'json' ? $value : array_map(
                fn ($item) => match ($parameter->items['type'] ?? 'string') {
                    'integer' => (int) $item,
                    'number' => (float) $item,
                    default => (string) $item,
                },
                $value
            ),
            'file' => $value instanceof UploadedFile ? $value : null,
            default => $value,
        };
    }

    /**
     * Non-blocking hints, e.g. an amount below the documented minimum.
     */
    private function warnings(Endpoint $endpoint, array $values, ExplorerContext $context): array
    {
        $warnings = [];
        $currency = $context->currency;

        if (isset($values['currency']) && $values['currency'] !== $currency->code) {
            $currency = $context->country->currencies->firstWhere('code', $values['currency']) ?? $currency;
        }

        if (isset($values['amount']) && is_numeric($values['amount']) && $currency->minimum_amount > 0
            && (int) $values['amount'] < $currency->minimum_amount && in_array($endpoint->key, ['transaction.initialize', 'transaction.charge_authorization', 'charge.create'], true)) {
            $warnings[] = sprintf(
                'Amount %d is below Paystack\'s documented transaction minimum for %s (%d = %s).',
                $values['amount'], $currency->code, $currency->minimum_amount, $currency->formatSubunit($currency->minimum_amount)
            );
        }

        return $warnings;
    }

    private function messages(ExplorerContext $context): array
    {
        return [
            'required' => ':Attribute is required.',
            'integer' => ':Attribute must be an integer.',
            'params.amount.min' => ':Attribute must be a positive integer.',
            'params.price.min' => ':Attribute must be a positive integer.',
            'params.*.*.amount.min' => ':Attribute must be a positive integer.',
            'params.*.*.array' => ':Attribute must be a JSON object.',
            'params.amount.regex' => ':Attribute must be a positive integer (in the currency subunit).',
            'email' => ':Attribute must be a valid email address.',
            'url' => ':Attribute must be a fully qualified URL.',
            'params.currency.in' => "Currency is invalid for the selected country ({$context->country->name}).",
            'params.channels.*.in' => "Channel :input is not available in {$context->country->name}.",
            'in' => 'The selected :attribute is invalid for this endpoint in '.$context->country->name.'.',
        ];
    }
}
