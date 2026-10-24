<?php

declare(strict_types=1);

namespace App\Services\Dedupe;

use App\Models\Outlet;
use App\Services\Geo\BoundingBox;
use App\Services\Geo\Haversine;

/** In-memory spatial grid of outlets for radius lookups without per-row queries. */
final class OutletIndex
{
    private const CELL_DEG = 0.01;

    /** @var array<string, list<int>> cell key => indexes into */
    private array $cells = [];

    /** @var list<array{id: int, code: string, name: string, norm: string, lat: float, lng: float, city: string, province_id: ?int, active: bool}> */
    private array $items = [];

    /** @param iterable<Outlet> $outlets */
    public function __construct(iterable $outlets = [])
    {
        foreach ($outlets as $outlet) {
            $this->add($outlet);
        }
    }

    public function add(Outlet $outlet): void
    {
        if ($outlet->lat === null || $outlet->lng === null) {
            return;
        }
        $i = count($this->items);
        $this->items[] = [
            'id' => (int) $outlet->id,
            'code' => (string) $outlet->code,
            'name' => (string) $outlet->name,
            'norm' => NameNormalizer::normalize((string) $outlet->name),
            'lat' => (float) $outlet->lat,
            'lng' => (float) $outlet->lng,
            'city' => (string) $outlet->city,
            'province_id' => $outlet->province_id !== null ? (int) $outlet->province_id : null,
            'active' => (bool) $outlet->is_active,
        ];
        $this->cells[self::key($outlet->lat, $outlet->lng)][] = $i;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Outlets within $radiusM of a point, nearest first.
     *
     * @return list<array{outlet: array{id: int, code: string, name: string, norm: string, lat: float, lng: float, city: string, province_id: ?int, active: bool}, distance: float}>
     */
    public function within(float $lat, float $lng, float $radiusM, bool $activeOnly = false): array
    {
        if ($this->items === []) {
            return [];
        }
        $box = BoundingBox::around($lat, $lng, $radiusM);
        $found = [];
        for ($cy = (int) floor($box->south / self::CELL_DEG); $cy <= (int) floor($box->north / self::CELL_DEG); $cy++) {
            for ($cx = (int) floor($box->west / self::CELL_DEG); $cx <= (int) floor($box->east / self::CELL_DEG); $cx++) {
                foreach ($this->cells[$cy.':'.$cx] ?? [] as $i) {
                    $item = $this->items[$i];
                    if ($activeOnly && ! $item['active']) {
                        continue;
                    }
                    $d = Haversine::distance($lat, $lng, $item['lat'], $item['lng']);
                    if ($d <= $radiusM) {
                        $found[] = ['outlet' => $item, 'distance' => $d];
                    }
                }
            }
        }
        usort($found, fn (array $a, array $b) => $a['distance'] <=> $b['distance']);

        return $found;
    }

    private static function key(float $lat, float $lng): string
    {
        return (int) floor($lat / self::CELL_DEG).':'.(int) floor($lng / self::CELL_DEG);
    }
}
