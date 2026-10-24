<?php

use App\DTO\BusinessDTO;
use App\Models\Outlet;
use App\Services\Dedupe\Deduplicator;
use App\Services\Dedupe\OutletIndex;

function dedupOutlet(string $name, float $lat = -6.2, float $lng = 106.8, bool $active = true): Outlet
{
    $o = new Outlet(['code' => 'O'.random_int(1, 99999), 'name' => $name, 'city' => 'Jakarta', 'lat' => $lat, 'lng' => $lng, 'is_active' => $active]);
    $o->id = random_int(1, 99999);

    return $o;
}

function dedupBiz(string $name, float $lat, float $lng, string $provider = 'osm', string $pid = 'node/1', array $extra = []): BusinessDTO
{
    return new BusinessDTO(
        provider: $provider, placeId: $pid, name: $name, categoryKey: 'cafe', lat: $lat, lng: $lng,
        phone: $extra['phone'] ?? null, address: $extra['address'] ?? null, rating: $extra['rating'] ?? null,
    );
}

/** ~ metres north of the base latitude. */
function northOf(float $lat, float $metres): float
{
    return $lat + $metres / 111_195;
}

it('applies the TRD thresholds', function () {
    expect(Deduplicator::isSameMerchant(80, 0.85))->toBeTrue();
    expect(Deduplicator::isSameMerchant(80, 0.79))->toBeFalse();
    expect(Deduplicator::isSameMerchant(101, 0.99))->toBeFalse();
    expect(Deduplicator::isSameMerchant(25, 0.6))->toBeTrue();
    expect(Deduplicator::isSameMerchant(25, 0.55))->toBeFalse();
    expect(Deduplicator::isSameMerchant(50, 0.7))->toBeFalse();
});

it('excludes a business that is a Hitachi merchant and keeps others', function () {
    $dedup = Deduplicator::forOutlets([dedupOutlet('Kopi Senja Raya')]);

    $same = dedupBiz('Toko Kopi Senja Raya', northOf(-6.2, 60), 106.8, pid: 'node/1');
    $differentName = dedupBiz('Bengkel Maju', northOf(-6.2, 20), 106.8, pid: 'node/2');
    $far = dedupBiz('Kopi Senja Raya', northOf(-6.2, 400), 106.8, pid: 'node/3');
    $nearTypo = dedupBiz('Kopi Senja', northOf(-6.2, 10), 106.8, pid: 'node/4');

    $r = $dedup->filter([$same, $differentName, $far, $nearTypo]);

    expect(array_map(fn ($b) => $b->placeId, $r['excluded']))->toBe(['node/1', 'node/4']);
    expect(array_map(fn ($b) => $b->placeId, $r['kept']))->toBe(['node/2', 'node/3']);
});

it('returns the matched outlet with distance and similarity', function () {
    $dedup = Deduplicator::forOutlets([dedupOutlet('Kopi Senja Raya')]);
    $m = $dedup->matchingOutlet('Kopi Senja Raya', northOf(-6.2, 40), 106.8);
    expect($m)->not->toBeNull()
        ->and($m['distance'])->toBeGreaterThan(35)->toBeLessThan(45)
        ->and($m['similarity'])->toBe(1.0);
    expect($dedup->matchingOutlet('Kopi Senja Raya', -6.3, 106.9))->toBeNull();
});

it('handles an empty outlet index and unlocated outlets', function () {
    $dedup = Deduplicator::forOutlets([]);
    expect($dedup->filter([dedupBiz('x', -6.2, 106.8)])['kept'])->toHaveCount(1);

    $index = new OutletIndex([new Outlet(['code' => 'N', 'name' => 'N', 'city' => 'c'])]);
    expect($index->count())->toBe(0);
});

it('index returns nearest first and can filter inactive outlets', function () {
    $index = new OutletIndex([
        dedupOutlet('Far', northOf(-6.2, 400), 106.8),
        dedupOutlet('Near', northOf(-6.2, 100), 106.8, active: false),
        dedupOutlet('Mid', northOf(-6.2, 250), 106.8),
    ]);
    $all = $index->within(-6.2, 106.8, 500);
    expect(array_map(fn ($h) => $h['outlet']['name'], $all))->toBe(['Near', 'Mid', 'Far']);
    $active = $index->within(-6.2, 106.8, 500, activeOnly: true);
    expect(array_map(fn ($h) => $h['outlet']['name'], $active))->toBe(['Mid', 'Far']);
    expect($index->within(-6.2, 106.8, 50))->toBe([]);
});

it('merges cross-provider duplicates within 40 m and 0.85 similarity', function () {
    $dedup = Deduplicator::forOutlets([]);
    $osm = dedupBiz('Kopi Senja Raya', -6.2, 106.8, 'osm', 'node/1', ['phone' => null, 'address' => 'Jl. A']);
    $google = dedupBiz('Kopi Senja Raya', northOf(-6.2, 20), 106.8, 'google', 'g1', ['phone' => '021123', 'rating' => 4.5]);
    $distinct = dedupBiz('Bengkel Lain', northOf(-6.2, 20), 106.8, 'google', 'g2');
    $tooFar = dedupBiz('Kopi Senja Raya', northOf(-6.2, 90), 106.8, 'google', 'g3');

    $merged = $dedup->mergeAcrossProviders([[$osm], [$google, $distinct, $tooFar]]);

    expect($merged)->toHaveCount(3);
    expect($merged[0]->provider)->toBe('osm')
        ->and($merged[0]->phone)->toBe('021123')
        ->and($merged[0]->rating)->toBe(4.5)
        ->and($merged[0]->address)->toBe('Jl. A')
        ->and($merged[0]->rawTags['merged_from'])->toBe(['google:g1']);
});

it('never merges two businesses of the same provider', function () {
    $dedup = Deduplicator::forOutlets([]);
    $a = dedupBiz('Kopi Senja', -6.2, 106.8, 'osm', 'node/1');
    $b = dedupBiz('Kopi Senja', -6.2, 106.8, 'osm', 'node/2');
    expect($dedup->mergeAcrossProviders([[$a, $b]]))->toHaveCount(2);
});
