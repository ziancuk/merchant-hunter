<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DataSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The key value is never exposed, only has_key and a masked hint (D-55).
 *
 * @mixin DataSource
 */
class DataSourceResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'priority' => $this->priority,
            'has_key' => $this->hasKey(),
            'key_hint' => $this->keyHint(),
            'monthly_quota' => $this->monthly_quota,
            'calls_this_month' => $this->calls_this_month,
            'cost_search_per_1000' => $this->cost_search_per_1000,
            'cost_details_per_1000' => $this->cost_details_per_1000,
            'last_run_at' => Support::iso($this->last_run_at),
            'last_status' => $this->last_status,
            'last_error' => $this->last_error,
            'config' => $this->config,
        ];
    }
}
