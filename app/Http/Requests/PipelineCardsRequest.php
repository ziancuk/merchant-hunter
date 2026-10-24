<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PipelineStatus;
use App\Repositories\ProspectFilter;
use Illuminate\Validation\Rule;

final class PipelineCardsRequest extends ProspectFilterRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->filterRules() + [
            'status' => ['nullable', Rule::enum(PipelineStatus::class)],
            'per_status' => ['nullable', 'integer', 'between:1,200'],
            'cursor' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function filter(): ProspectFilter
    {
        return ProspectFilter::from(array_filter($this->validated(), fn ($v) => $v !== null));
    }
}
