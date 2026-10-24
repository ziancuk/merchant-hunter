<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\GeocodeStatus;
use Illuminate\Validation\Rule;

final class OutletIndexRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'geocode_status' => ['nullable', Rule::enum(GeocodeStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
