<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class RoutePlanRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'prospect_ids' => ['required', 'array', 'min:2', 'max:30'],
            'prospect_ids.*' => ['integer', 'distinct'],
            'start' => ['nullable', 'array'],
            'start.lat' => ['required_with:start', 'numeric', 'between:-90,90'],
            'start.lng' => ['required_with:start', 'numeric', 'between:-180,180'],
        ];
    }
}
