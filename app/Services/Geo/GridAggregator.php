<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Enums\ScoreBand;

/** Buckets points into square lat/lng cells for the low-zoom map payload. */
final class GridAggregator
{
    public static function cellSizeForZoom(int $zoom): float
    {
        return match (true) {
            $zoom <= 2 => 4.0,
            $zoom <= 3 => 2.0,
            $zoom <= 4 => 1.0,
            default => 0.5,
        };
    }

    public static function cellOrigin(float $value, float $size): float
    {
        return round(floor($value / $size) * $size, 6);
    }

    /**
     * @param  iterable<array{lat: float, lng: float, score: int}>  $points
     * @return list<array{lng: float, lat: float, count: int, avg_score: float, hot: int}>
     */
    public static function aggregate(iterable $points, float $size): array
    {
        $cells = [];
        foreach ($points as $p) {
            $lat = self::cellOrigin((float) $p['lat'], $size);
            $lng = self::cellOrigin((float) $p['lng'], $size);
            $key = $lat.':'.$lng;
            $cells[$key] ??= ['lng' => $lng, 'lat' => $lat, 'count' => 0, 'sum' => 0, 'hot' => 0];
            $cells[$key]['count']++;
            $cells[$key]['sum'] += (int) $p['score'];
            $cells[$key]['hot'] += $p['score'] >= ScoreBand::HOT_MIN ? 1 : 0;
        }

        return array_values(array_map(fn (array $c) => [
            'lng' => $c['lng'],
            'lat' => $c['lat'],
            'count' => $c['count'],
            'avg_score' => round($c['sum'] / $c['count'], 1),
            'hot' => $c['hot'],
        ], $cells));
    }
}
