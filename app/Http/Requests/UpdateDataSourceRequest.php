<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class UpdateDataSourceRequest extends ApiRequest
{
    /** Keys accepted inside `config` (D-121). No provider reads them yet; anything else is refused. */
    public const CONFIG_KEYS = ['region', 'language', 'radius_m', 'timeout_s', 'notes'];

    public const CONFIG_MAX_BYTES = 2048;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'between:0,1000'],
            'api_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'clear_key' => ['sometimes', 'boolean'],
            'monthly_quota' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cost_search_per_1000' => ['sometimes', 'nullable', 'numeric', 'between:0,10000'],
            'cost_details_per_1000' => ['sometimes', 'nullable', 'numeric', 'between:0,10000'],
            'config' => ['sometimes', 'nullable', 'array', 'max:'.count(self::CONFIG_KEYS), function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_array($value)) {
                    return;
                }
                $unknown = array_diff(array_map('strval', array_keys($value)), self::CONFIG_KEYS);
                if ($unknown !== []) {
                    $fail(__('requests.messages.config_unknown', ['keys' => implode(', ', self::CONFIG_KEYS)]));
                }
                if (strlen((string) json_encode($value)) > self::CONFIG_MAX_BYTES) {
                    $fail(__('requests.messages.config_too_large', ['max' => self::CONFIG_MAX_BYTES]));
                }
            }],
            'config.region' => ['sometimes', 'nullable', 'string', 'max:10'],
            'config.language' => ['sometimes', 'nullable', 'string', 'max:10'],
            'config.radius_m' => ['sometimes', 'nullable', 'integer', 'between:100,50000'],
            'config.timeout_s' => ['sometimes', 'nullable', 'integer', 'between:1,60'],
            'config.notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return (array) __('requests.attributes.data_source');
    }
}
