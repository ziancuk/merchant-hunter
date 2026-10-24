<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class DigestRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['date' => ['nullable', 'date_format:Y-m-d']];
    }
}
