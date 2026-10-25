<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\BulkProspectAction;
use App\Enums\PipelineStatus;
use Illuminate\Validation\Rule;

final class BulkRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.BulkProspectAction::MAX_IDS],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:status,opt_out'],
            'status' => ['required_if:action,status', 'nullable', Rule::enum(PipelineStatus::class)],
            'rejection_reason_id' => ['required_if:status,lost', 'nullable', 'integer', 'exists:rejection_reasons,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['ids.max' => __('requests.messages.ids_max', ['max' => BulkProspectAction::MAX_IDS])] + parent::messages();
    }
}
