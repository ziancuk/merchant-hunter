<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class ContactRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:1000'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
