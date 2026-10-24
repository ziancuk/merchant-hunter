<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\DTO\BusinessDTO;
use App\Models\Category;
use App\Models\DataSource;
use App\Models\Outlet;
use App\Models\Province;
use App\Services\Providers\DataProvider;
use App\Services\Providers\ProviderRegistry;
use Carbon\CarbonImmutable;

final class SyncFixtures
{
    public const LAT = -6.2;

    public const LNG = 106.8;

    /** @return array{province: Province, cafe: Category, bakery: Category} */
    public static function world(): array
    {
        DataSource::query()->delete();
        DataSource::create(['key' => 'osm', 'name' => 'OSM', 'is_active' => true, 'priority' => 10]);
        DataSource::create(['key' => 'google', 'name' => 'Google', 'is_active' => false, 'priority' => 5]);
        DataSource::create(['key' => 'mock', 'name' => 'Mock', 'is_active' => false, 'priority' => 90]);

        return [
            'province' => Province::factory()->create(['code' => 'JK', 'name' => 'DKI Jakarta']),
            'cafe' => Category::factory()->create(['key' => 'cafe', 'weight' => 80]),
            'bakery' => Category::factory()->create(['key' => 'bakery', 'weight' => 70]),
        ];
    }

    public static function outlet(Province $province, string $code = 'OUT-1', float $lat = self::LAT, float $lng = self::LNG, array $extra = []): Outlet
    {
        return Outlet::factory()->create(array_merge([
            'code' => $code, 'name' => 'Outlet '.$code.' Merchant', 'city' => 'Jakarta Pusat',
            'province_id' => $province->id, 'lat' => $lat, 'lng' => $lng,
        ], $extra));
    }

    /** Offset a coordinate by metres (north, east). */
    public static function at(float $northM, float $eastM = 0.0): array
    {
        return [self::LAT + $northM / 111_195, self::LNG + $eastM / (111_195 * cos(deg2rad(self::LAT)))];
    }

    public static function biz(string $id, float $northM = 300, float $eastM = 0, array $o = []): BusinessDTO
    {
        [$lat, $lng] = self::at($northM, $eastM);

        return new BusinessDTO(
            provider: $o['provider'] ?? 'osm',
            placeId: $id,
            name: $o['name'] ?? 'Kedai '.$id,
            categoryKey: $o['category'] ?? 'cafe',
            lat: $lat,
            lng: $lng,
            address: $o['address'] ?? null,
            phone: $o['phone'] ?? null,
            website: $o['website'] ?? null,
            rating: $o['rating'] ?? null,
            reviewCount: $o['reviews'] ?? null,
            isClosedPermanently: $o['closed'] ?? false,
            simulatedFields: $o['simulated'] ?? [],
            fetchedAt: CarbonImmutable::now(),
        );
    }

    /** Install providers (keyed by their key()) in a fresh registry bound in the container. */
    public static function registry(DataProvider ...$providers): ProviderRegistry
    {
        $registry = new ProviderRegistry;
        foreach ($providers as $p) {
            $registry->extend($p->key(), $p);
        }
        app()->instance(ProviderRegistry::class, $registry);

        return $registry;
    }
}
