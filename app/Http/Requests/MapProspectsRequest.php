<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class MapProspectsRequest extends ProspectFilterRequest
{
    use BboxRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->filterRules() + [
            'bbox' => ['required', 'string'],
            'zoom' => ['required', 'integer', 'between:0,22'],
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateBbox($validator);
    }
}
