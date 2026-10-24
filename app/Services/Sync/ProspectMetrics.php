<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Services\Dedupe\OutletIndex;

/** Distance / neighbour / density metrics of a prospect (TRD §7 inputs), computed from in-memory indexes. */
final class ProspectMetrics
{
    public const NEAREST_SEARCH_M = 5000;

    public const NEIGHBOR_RADIUS_M = 500;

    public function __construct(private readonly OutletIndex $outlets) {}

    /**
     * @return array{nearest_outlet_id: ?int, nearest_outlet_distance_m: ?int, neighbor_count_500m: int, province_id: ?int, city: ?string}
     */
    public function outletMetrics(float $lat, float $lng): array
    {
        $nearest = $this->outlets->within($lat, $lng, self::NEAREST_SEARCH_M)[0] ?? null;
        $neighbors = $this->outlets->within($lat, $lng, self::NEIGHBOR_RADIUS_M, activeOnly: true);

        return [
            'nearest_outlet_id' => $nearest['outlet']['id'] ?? null,
            'nearest_outlet_distance_m' => $nearest !== null ? (int) round($nearest['distance']) : null,
            'neighbor_count_500m' => count($neighbors),
            'province_id' => $nearest['outlet']['province_id'] ?? null,
            'city' => $nearest['outlet']['city'] ?? null,
        ];
    }

    public function density(PointGrid $grid, float $lat, float $lng, ?string $selfKey): int
    {
        return $grid->countWithin($lat, $lng, self::NEIGHBOR_RADIUS_M, $selfKey);
    }
}
