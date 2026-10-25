<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\DataSource;
use Illuminate\Validation\ValidationException;

final class UpdateDataSourceAction
{
    /** @param array<string, mixed> $data validated */
    public function handle(DataSource $source, array $data): DataSource
    {
        $source->fill(array_intersect_key($data, array_flip([
            'is_active', 'priority', 'monthly_quota', 'cost_search_per_1000', 'cost_details_per_1000', 'config',
        ])));

        if (! empty($data['clear_key'])) {
            $source->api_key = null;
        } elseif (filled($data['api_key'] ?? null)) {
            $source->api_key = (string) $data['api_key'];
        }

        if ($source->key === 'google' && $source->is_active && ! $source->hasKey()) {
            throw ValidationException::withMessages(['is_active' => [__('api.google_needs_key')]]);
        }

        $source->save();

        return $source->refresh();
    }
}
