<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class PipelineTableRequest extends ProspectFilterRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->filterRules() + [
            'sort' => ['nullable', 'in:score,last_contacted_at,name'],
            'dir' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
