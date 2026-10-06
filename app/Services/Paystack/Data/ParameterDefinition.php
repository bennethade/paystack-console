<?php

namespace App\Services\Paystack\Data;

/**
 * One request parameter of an endpoint, as stored in paystack_endpoints.parameters.
 *
 *  in:       path | query | body
 *  type:     string | integer | number | boolean | array | object | file
 *  binding:  ties the parameter to the explorer context so it is pre-filled
 *            and validated against the selected market:
 *              currency       → selected currency (enum narrowed to the country's currencies)
 *              country_code   → selected country ISO code (e.g. NG)
 *              bank_country   → value accepted by GET /bank?country= (e.g. nigeria)
 *              channels       → enum narrowed to the country's channels
 *              recipient_type → enum narrowed to the country's transfer recipient types
 *              callback_url   → defaults to this app's payment callback page
 *  root:     the value IS the whole request body (for endpoints whose body
 *            is a JSON array, e.g. POST /bulkcharge), instead of one field
 *            of a body object.
 */
final class ParameterDefinition
{
    public const LOCATIONS = ['path', 'query', 'body'];

    public const TYPES = ['string', 'integer', 'number', 'boolean', 'array', 'object', 'file'];

    public const BINDINGS = ['currency', 'country_code', 'bank_country', 'channels', 'recipient_type', 'callback_url'];

    /**
     * @param  list<string|int>|null  $enum
     * @param  array<string, mixed>|null  $items
     * @param  list<array<string, mixed>>  $properties
     */
    public function __construct(
        public readonly string $name,
        public readonly string $in,
        public readonly string $type = 'string',
        public readonly ?string $format = null,
        public readonly bool $required = false,
        public readonly ?string $description = null,
        public readonly ?array $enum = null,
        public readonly mixed $default = null,
        public readonly mixed $example = null,
        public readonly ?array $items = null,
        public readonly array $properties = [],
        public readonly ?string $binding = null,
        public readonly bool $sensitive = false,
        public readonly bool $root = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            in: in_array($data['in'] ?? 'body', self::LOCATIONS, true) ? $data['in'] : 'body',
            type: in_array($data['type'] ?? 'string', self::TYPES, true) ? $data['type'] : 'string',
            format: $data['format'] ?? null,
            required: (bool) ($data['required'] ?? false),
            description: $data['description'] ?? null,
            enum: isset($data['enum']) && is_array($data['enum']) ? array_values($data['enum']) : null,
            default: $data['default'] ?? null,
            example: $data['example'] ?? null,
            items: $data['items'] ?? null,
            properties: $data['properties'] ?? [],
            binding: in_array($data['binding'] ?? null, self::BINDINGS, true) ? $data['binding'] : null,
            sensitive: (bool) ($data['sensitive'] ?? false),
            root: (bool) ($data['root'] ?? false),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'in' => $this->in,
            'type' => $this->type,
            'format' => $this->format,
            'required' => $this->required,
            'description' => $this->description,
            'enum' => $this->enum,
            'default' => $this->default,
            'example' => $this->example,
            'items' => $this->items,
            'properties' => $this->properties ?: null,
            'binding' => $this->binding,
            'sensitive' => $this->sensitive ?: null,
            'root' => $this->root ?: null,
            'ui' => $this->ui(),
        ], fn ($v) => $v !== null);
    }

    /**
     * The form control used to edit this parameter.
     */
    public function ui(): string
    {
        $itemType = $this->items['type'] ?? 'string';

        return match (true) {
            $this->type === 'file' => 'file',
            $this->type === 'boolean' => 'toggle',
            $this->isJsonLike() => 'json',
            $this->type === 'array' && ! empty($this->items['enum']) => 'multiselect',
            $this->type === 'array' && in_array($itemType, ['string', 'integer', 'number'], true) => 'repeatable',
            $this->type === 'array' => 'json',
            ! empty($this->enum) => 'select',
            in_array($this->type, ['integer', 'number'], true) => 'number',
            $this->format === 'email' => 'email',
            $this->format === 'uri' => 'url',
            $this->format === 'date' => 'date',
            $this->format === 'date-time' => 'datetime',
            default => 'text',
        };
    }

    /**
     * Objects, and string fields Paystack documents as "stringified JSON"
     * (metadata, custom_fields), are edited with the JSON editor.
     */
    public function isJsonLike(): bool
    {
        return $this->type === 'object'
            || ($this->type === 'string' && in_array($this->name, ['metadata', 'custom_fields'], true));
    }
}
