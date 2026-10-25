<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Repositories\ReportQuery;
use Illuminate\Validation\Rule;

final class ReportTableRequest extends ProspectFilterRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->filterRules() + [
            'sort' => ['nullable', Rule::in(array_keys(ReportQuery::SORTS))],
            'dir' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
