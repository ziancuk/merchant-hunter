<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class UpdateSettingsRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contact' => ['sometimes', 'array'],
            'contact.warn_days' => ['sometimes', 'integer', 'between:0,90'],
            'contact.min_interval_days' => ['sometimes', 'integer', 'between:0,365'],
            'contact.max_overrides' => ['sometimes', 'integer', 'between:0,20'],
            'digest' => ['sometimes', 'array'],
            'digest.email_to' => ['sometimes', 'nullable', 'email', 'max:190'],
            'digest.hot_threshold' => ['sometimes', 'integer', 'between:1,100'],
            'digest.follow_up_days' => ['sometimes', 'integer', 'between:1,90'],
            'sync' => ['sometimes', 'array'],
            'sync.max_outlets_per_run' => ['sometimes', 'integer', 'between:1,1000'],
            'scoring' => ['sometimes', 'array'],
            'scoring.params' => ['sometimes', 'nullable', 'array'],
            'enrichment' => ['sometimes', 'array'],
            'enrichment.simulated' => ['sometimes', 'boolean'],
            'demo' => ['sometimes', 'array'],
            'demo.allow_simulated_whatsapp' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Human labels so validation messages never expose internal keys like `contact.warn_days` (B-07).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'contact' => 'Aturan kontak',
            'contact.warn_days' => 'Peringatan kontak terakhir',
            'contact.min_interval_days' => 'Jarak minimum antar kontak',
            'contact.max_overrides' => 'Maksimum override per prospek',
            'digest' => 'Digest',
            'digest.email_to' => 'Email penerima digest',
            'digest.hot_threshold' => 'Ambang skor panas',
            'digest.follow_up_days' => 'Hari tindak lanjut',
            'sync' => 'Sinkronisasi',
            'sync.max_outlets_per_run' => 'Maksimum outlet per run',
            'scoring' => 'Penilaian',
            'scoring.params' => 'Parameter penilaian',
            'enrichment' => 'Pengayaan data',
            'enrichment.simulated' => 'Data simulasi',
            'demo' => 'Demo',
            'demo.allow_simulated_whatsapp' => 'WhatsApp nomor simulasi',
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function settings(): array
    {
        $v = $this->validated();
        if (array_key_exists('scoring', $v)) {
            $v['scoring']['params'] = $v['scoring']['params'] ?? [];
        }

        return $v;
    }
}
