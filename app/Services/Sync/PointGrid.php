<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Services\Geo\BoundingBox;
use App\Services\Geo\Haversine;

/** In-memory spatial grid for radius counting without per-row queries (D-50: bbox + haversine in PHP). */
final class PointGrid
{
    private const CELL_DEG = 0.005;

    /** @var array<string, array<string, array{0: float, 1: float}>> cell => key => [lat, lng] */
    private array $cells = [];

    /** @var array<string, string> key => cell */
    private array $where = [];

    public function add(string $key, float $lat, float $lng): void
    {
        if (isset($this->where[$key])) {
            unset($this->cells[$this->where[$key]][$key]);
        }
        $cell = self::cell($lat, $lng);
        $this->cells[$cell][$key] = [$lat, $lng];
        $this->where[$key] = $cell;
    }

    public function remove(string $key): void
    {
        if (isset($this->where[$key])) {
            unset($this->cells[$this->where[$key]][$key], $this->where[$key]);
        }
    }

    public function count(): int
    {
        return count($this->where);
    }

    /** Points other than $excludeKey within $radiusM of a coordinate. */
    public function countWithin(float $lat, float $lng, float $radiusM, ?string $excludeKey = null): int
    {
        $n = 0;
        foreach ($this->cellsAround($lat, $lng, $radiusM) as $cell) {
            foreach ($this->cells[$cell] ?? [] as $key => [$plat, $plng]) {
                if ($key !== $excludeKey && Haversine::distance($lat, $lng, $plat, $plng) <= $radiusM) {
                    $n++;
                }
            }
        }

        return $n;
    }

    /** @return list<string> */
    private function cellsAround(float $lat, float $lng, float $radiusM): array
    {
        $box = BoundingBox::around($lat, $lng, $radiusM);
        $out = [];
        for ($cy = (int) floor($box->south / self::CELL_DEG); $cy <= (int) floor($box->north / self::CELL_DEG); $cy++) {
            for ($cx = (int) floor($box->west / self::CELL_DEG); $cx <= (int) floor($box->east / self::CELL_DEG); $cx++) {
                $out[] = $cy.':'.$cx;
            }
        }

        return $out;
    }

    private static function cell(float $lat, float $lng): string
    {
        return (int) floor($lat / self::CELL_DEG).':'.(int) floor($lng / self::CELL_DEG);
    }
}
