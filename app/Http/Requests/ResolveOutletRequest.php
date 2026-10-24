<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class ResolveOutletRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'candidate_index' => ['required_without:lat', 'nullable', 'integer', 'min:0'],
            'lat' => ['required_without:candidate_index', 'nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['required_without:candidate_index', 'nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ];
    }
}
