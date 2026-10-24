<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class AiMessageRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['tone' => ['nullable', 'in:formal,ramah']];
    }
}
