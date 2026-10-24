<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Outlet */
class OutletResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'province_id' => $this->province_id,
            'province' => $this->relationLoaded('province') && $this->province ? $this->province->name : null,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'geocode_source' => $this->geocode_source,
            'geocode_status' => $this->geocode_status->value,
            'geocode_score' => $this->geocode_score,
            'is_active' => (bool) $this->is_active,
            'is_simulated' => (bool) $this->is_simulated,
            'last_synced_at' => Support::iso($this->last_synced_at),
        ];
    }
}
