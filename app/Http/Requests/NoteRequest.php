<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class NoteRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:1', 'max:2000']];
    }
}
