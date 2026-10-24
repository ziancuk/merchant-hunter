<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class ImportOutletsRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['file.max' => 'Ukuran berkas maksimal 2 MB.'] + parent::messages();
    }
}
