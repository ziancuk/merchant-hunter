<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class WhitespaceRequest extends ApiRequest
{
    use BboxRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bbox' => ['required', 'string'],
            'category_id' => ['nullable', 'array', 'max:50'],
            'category_id.*' => ['integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateBbox($validator);
    }

    /** @return list<int> */
    public function categoryIds(): array
    {
        return array_values(array_map('intval', (array) ($this->validated()['category_id'] ?? [])));
    }
}
