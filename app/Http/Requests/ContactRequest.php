<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class ContactRequest extends ApiRequest
{
    /** @return array<string, string> */
    public function messages(): array
    {
        return ['override_reason.min' => 'Alasan override minimal 5 karakter.'] + parent::messages();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:1000'],
            'override_reason' => ['nullable', 'string', 'min:5', 'max:500'],
        ];
    }
}
