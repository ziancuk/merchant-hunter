<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

final class OutletRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $outlet = $this->route('outlet');

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('outlets', 'code')->ignore($outlet?->id)],
            'name' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:80'],
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
