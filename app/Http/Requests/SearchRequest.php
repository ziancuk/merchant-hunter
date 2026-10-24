<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class SearchRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['q' => ['required', 'string', 'min:2', 'max:100']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['q.min' => 'Kata kunci minimal 2 karakter.'] + parent::messages();
    }
}
