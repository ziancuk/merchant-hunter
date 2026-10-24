<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class ProspectExportRequest extends ProspectFilterRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->filterRules();
    }
}
