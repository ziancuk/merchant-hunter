<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class MapOutletsRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['province_id' => ['nullable', 'integer', 'exists:provinces,id']];
    }
}
