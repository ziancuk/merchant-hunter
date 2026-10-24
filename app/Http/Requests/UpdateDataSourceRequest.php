<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class UpdateDataSourceRequest extends ApiRequest
{
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
            'config' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
