<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Prospect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Prospect */
class CardResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category?->name,
            'city' => $this->city,
            'score' => $this->score,
            'score_band' => $this->scoreBand()->value,
            'phone_present' => filled($this->phone),
            'last_contacted_at' => Support::iso($this->last_contacted_at),
            'handled_by' => $this->handler?->name,
            'badges' => Support::badges($this->resource),
            'do_not_contact' => (bool) $this->do_not_contact,
        ];
    }
}
