<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\LifecycleStatus;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Services\Geo\BoundingBox;
use App\Services\Geo\Haversine;
use App\Services\Sync\WhitespaceCache;
use Illuminate\Support\Facades\Cache;

/**
 * White space (D-61): grid cells with >= 15 open prospects and no outlet within 500 m of the cell centre.
 * Cells are 0.01 degrees (about 1.1 km). Cached 10 minutes; BE-Provider-Scheduler bumps `hunter.whitespace.version`.
 */
final class WhiteSpaceQuery
{
    public const CELL_DEG = 0.01;

    public const MIN_PROSPECTS = 15;

    public const MIN_OUTLET_DISTANCE_M = 500;

    public const TTL_SECONDS = 600;

    /**
     * @param  list<int>  $categoryIds
     * @return list<array{id: string, lng: float, lat: float, prospects: int, avg_score: int, nearest_outlet_m: int|null, province_id: int|null}>
     */
    public function cells(BoundingBox $bbox, array $categoryIds = []): array
    {
        sort($categoryIds);
        $key = sprintf(
            'hunter.whitespace:v%d:%s:%s',
            WhitespaceCache::version(),
            implode(',', [round($bbox->west, 2), round($bbox->south, 2), round($bbox->east, 2), round($bbox->north, 2)]),
            implode(',', $categoryIds),
        );

        return Cache::remember($key, self::TTL_SECONDS, fn () => $this->compute($bbox, $categoryIds));
    }

    /**
     * @param  list<int>  $categoryIds
     * @return list<array{id: string, lng: float, lat: float, prospects: int, avg_score: int, nearest_outlet_m: int|null, province_id: int|null}>
     */
    private function compute(BoundingBox $bbox, array $categoryIds): array
    {
        $rows = Prospect::query()
            ->where('lifecycle_status', LifecycleStatus::Open->value)
            ->whereNotNull('lat')->whereNotNull('lng')
            ->whereBetween('lat', [$bbox->south, $bbox->north])
            ->whereBetween('lng', [$bbox->west, $bbox->east])
            ->when($categoryIds !== [], fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->select(['lat', 'lng', 'score'])
            ->toBase()
            ->cursor();

        $cells = [];
        foreach ($rows as $r) {
            $iy = (int) floor(round((float) $r->lat / self::CELL_DEG, 4));
            $ix = (int) floor(round((float) $r->lng / self::CELL_DEG, 4));
            $k = $iy.':'.$ix;
            $cells[$k] ??= ['iy' => $iy, 'ix' => $ix, 'n' => 0, 'sum' => 0];
            $cells[$k]['n']++;
            $cells[$k]['sum'] += (int) $r->score;
        }

        $dense = array_filter($cells, fn (array $c) => $c['n'] >= self::MIN_PROSPECTS);
        if ($dense === []) {
            return [];
        }

        $outlets = Outlet::query()->active()->located()->toBase()->get(['lat', 'lng', 'province_id']);

        $result = [];
        foreach ($dense as $c) {
            $lat = ($c['iy'] + 0.5) * self::CELL_DEG;
            $lng = ($c['ix'] + 0.5) * self::CELL_DEG;

            $nearest = null;
            $province = null;
            foreach ($outlets as $o) {
                $d = Haversine::distance($lat, $lng, (float) $o->lat, (float) $o->lng);
                if ($nearest === null || $d < $nearest) {
                    $nearest = $d;
                    $province = (int) $o->province_id;
                }
            }
            if ($nearest !== null && $nearest <= self::MIN_OUTLET_DISTANCE_M) {
                continue;
            }

            $result[] = [
                'id' => sprintf('%.2f:%.2f', $c['iy'] * self::CELL_DEG, $c['ix'] * self::CELL_DEG),
                'lng' => round($lng, 4),
                'lat' => round($lat, 4),
                'prospects' => $c['n'],
                'avg_score' => (int) round($c['sum'] / $c['n']),
                'nearest_outlet_m' => $nearest === null ? null : (int) round($nearest),
                'province_id' => $province,
            ];
        }

        usort($result, fn (array $a, array $b) => $b['prospects'] <=> $a['prospects']);

        return $result;
    }
}
