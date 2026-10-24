<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PipelineStatus;
use App\Repositories\ProspectFilter;
use Illuminate\Validation\Rule;

/** Base for requests that accept the shared prospect filter (map, pipeline, export). */
abstract class ProspectFilterRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    protected function filterRules(): array
    {
        return [
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'city' => ['nullable', 'string', 'max:80'],
            'category_id' => ['nullable', 'array', 'max:50'],
            'category_id.*' => ['integer'],
            'score_min' => ['nullable', 'integer', 'between:0,100'],
            'score_max' => ['nullable', 'integer', 'between:0,100'],
            'rating_min' => ['nullable', 'numeric', 'between:0,5'],
            'has_phone' => ['nullable', 'boolean'],
            'pipeline_status' => ['nullable', 'array', 'max:5'],
            'pipeline_status.*' => [Rule::enum(PipelineStatus::class)],
            'max_distance_m' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'new_open_only' => ['nullable', 'boolean'],
            'include_closed' => ['nullable', 'boolean'],
        ];
    }

    public function filter(): ProspectFilter
    {
        return ProspectFilter::from(array_filter($this->validated(), fn ($v) => $v !== null));
    }
}
