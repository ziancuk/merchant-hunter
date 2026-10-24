<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SchedulerRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SchedulerRun */
class SchedulerRunResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'command' => $this->command,
            'status' => $this->status,
            'started_at' => Support::iso($this->started_at),
            'finished_at' => Support::iso($this->finished_at),
            'duration_ms' => $this->duration_ms,
            'outlets_total' => (int) $this->outlets_total,
            'outlets_ok' => (int) $this->outlets_ok,
            'outlets_failed' => (int) $this->outlets_failed,
            'prospects_new' => (int) $this->prospects_new,
            'prospects_updated' => (int) $this->prospects_updated,
            'prospects_closed' => (int) $this->prospects_closed,
            'api_calls' => (int) $this->api_calls,
            'error' => $this->error,
        ];

        if ($this->relationLoaded('outlets')) {
            $data['outlets'] = $this->outlets->map(fn ($o) => [
                'id' => $o->id,
                'outlet_id' => $o->outlet_id,
                'outlet' => $o->outlet?->name,
                'provider' => $o->provider,
                'status' => $o->status,
                'fetched_count' => (int) $o->fetched_count,
                'new_count' => (int) $o->new_count,
                'updated_count' => (int) $o->updated_count,
                'closed_count' => (int) $o->closed_count,
                'error' => $o->error,
                'duration_ms' => $o->duration_ms,
            ])->all();
        }

        return $data;
    }
}
