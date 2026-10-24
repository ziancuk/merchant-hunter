<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class PageRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['page' => ['nullable', 'integer', 'min:1']];
    }
}
