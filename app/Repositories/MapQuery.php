<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\LifecycleStatus;
use App\Enums\PipelineStatus;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Services\Geo\BoundingBox;
use App\Services\Geo\GridAggregator;
use Illuminate\Database\Eloquent\Builder;

/** Map payloads: one indexed query each, no relations loaded (TRD §8). */
final class MapQuery
{
    public const MAX_POINTS = 20000;

    public const GRID_BELOW_ZOOM = 6;

    public const FLAG_NEW_OPEN = 1;

    public const FLAG_SIMULATED = 2;

    public const FLAG_CLOSED = 4;

    public const FLAG_OPT_OUT = 8;

    /** @return array<string, mixed> */
    public function outlets(?int $provinceId = null): array
    {
        $rows = Outlet::query()
            ->active()->located()
            ->when($provinceId !== null, fn ($q) => $q->where('province_id', $provinceId))
            ->orderBy('id')
            ->toBase()
            ->get(['id', 'code', 'name', 'lng', 'lat', 'province_id']);

        return [
            'count' => $rows->count(),
            'ids' => $rows->pluck('id')->map(fn ($v) => (int) $v)->all(),
            'codes' => $rows->pluck('code')->all(),
            'names' => $rows->pluck('name')->all(),
            'lng' => $rows->pluck('lng')->map(fn ($v) => (float) $v)->all(),
            'lat' => $rows->pluck('lat')->map(fn ($v) => (float) $v)->all(),
            'province_ids' => $rows->pluck('province_id')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function prospects(BoundingBox $bbox, int $zoom, ProspectFilter $filter): array
    {
        return $zoom >= self::GRID_BELOW_ZOOM ? $this->points($bbox, $filter) : $this->grid($bbox, $zoom, $filter);
    }

    /** @return array<string, mixed> */
    private function points(BoundingBox $bbox, ProspectFilter $filter): array
    {
        $rows = $this->base($bbox, $filter)
            ->select(['id', 'lng', 'lat', 'score', 'pipeline_status', 'is_new_open', 'is_simulated', 'phone_simulated',
                'rating_simulated', 'reviews_simulated', 'lifecycle_status', 'do_not_contact'])
            ->orderByDesc('score')
            ->orderBy('id')
            ->limit(self::MAX_POINTS + 1)
            ->toBase()
            ->get();

        $truncated = $rows->count() > self::MAX_POINTS;
        if ($truncated) {
            $rows = $rows->take(self::MAX_POINTS);
        }

        $statusIndex = [];
        foreach (PipelineStatus::cases() as $case) {
            $statusIndex[$case->value] = $case->index();
        }

        $out = ['type' => 'points', 'count' => $rows->count(), 'truncated' => $truncated,
            'ids' => [], 'lng' => [], 'lat' => [], 'score' => [], 'status' => [], 'flags' => []];

        foreach ($rows as $r) {
            $flags = 0;
            $flags |= $r->is_new_open ? self::FLAG_NEW_OPEN : 0;
            $flags |= ($r->is_simulated || $r->phone_simulated || $r->rating_simulated || $r->reviews_simulated) ? self::FLAG_SIMULATED : 0;
            $flags |= $r->lifecycle_status === LifecycleStatus::Closed->value ? self::FLAG_CLOSED : 0;
            $flags |= $r->do_not_contact ? self::FLAG_OPT_OUT : 0;

            $out['ids'][] = (int) $r->id;
            $out['lng'][] = (float) $r->lng;
            $out['lat'][] = (float) $r->lat;
            $out['score'][] = (int) $r->score;
            $out['status'][] = $statusIndex[$r->pipeline_status] ?? 0;
            $out['flags'][] = $flags;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function grid(BoundingBox $bbox, int $zoom, ProspectFilter $filter): array
    {
        $size = GridAggregator::cellSizeForZoom($zoom);
        $rows = $this->base($bbox, $filter)->select(['lat', 'lng', 'score'])->toBase()->cursor();

        return [
            'type' => 'grid',
            'cell_size_deg' => $size,
            'cells' => GridAggregator::aggregate(
                (function () use ($rows) {
                    foreach ($rows as $r) {
                        yield ['lat' => (float) $r->lat, 'lng' => (float) $r->lng, 'score' => (int) $r->score];
                    }
                })(),
                $size,
            ),
        ];
    }

    /** @return Builder<Prospect> */
    private function base(BoundingBox $bbox, ProspectFilter $filter)
    {
        $q = Prospect::query()
            ->whereNotNull('prospects.lat')->whereNotNull('prospects.lng')
            ->whereBetween('prospects.lat', [$bbox->south, $bbox->north]);

        // A box that crosses the antimeridian is split in two longitude ranges.
        if ($bbox->west <= $bbox->east) {
            $q->whereBetween('prospects.lng', [$bbox->west, $bbox->east]);
        } else {
            $q->where(fn ($w) => $w->where('prospects.lng', '>=', $bbox->west)->orWhere('prospects.lng', '<=', $bbox->east));
        }

        return $filter->apply($q);
    }
}
