<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PipelineStatus;
use Illuminate\Validation\Rule;

final class ChangeStatusRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(PipelineStatus::class)],
            'rejection_reason_id' => ['required_if:status,lost', 'nullable', 'integer', 'exists:rejection_reasons,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['rejection_reason_id.required_if' => 'Alasan penolakan wajib dipilih bila status Kalah.'] + parent::messages();
    }
}
