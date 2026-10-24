<?php

use App\Services\Geo\BoundingBox;
use App\Services\Geo\GridAggregator;
use App\Services\Geo\Haversine;

it('computes haversine distance', function () {
    // Monas to Bundaran HI is roughly 2.7 km
    $d = Haversine::distance(-6.1754, 106.8272, -6.1950, 106.8230);
    expect($d)->toBeGreaterThan(2000)->toBeLessThan(2400);
    expect(Haversine::distance(1.0, 2.0, 1.0, 2.0))->toBe(0.0);
});

it('bounding box contains every point within the radius', function () {
    $box = BoundingBox::around(-6.2, 106.8, 2000);
    foreach ([0, 90, 180, 270] as $bearing) {
        $lat = -6.2 + 0.0179 * cos(deg2rad($bearing));
        $lng = 106.8 + 0.0179 * sin(deg2rad($bearing));
        expect(Haversine::distance(-6.2, 106.8, $lat, $lng))->toBeLessThanOrEqual(2000.0 + 1);
        expect($box->contains($lat, $lng))->toBeTrue();
    }
    expect($box->contains(-6.3, 106.8))->toBeFalse();
});

it('parses bbox strings', function () {
    expect(BoundingBox::parse('95,-11,141,6'))->toBeInstanceOf(BoundingBox::class)
        ->and(BoundingBox::parse('a,b'))->toBeNull()
        ->and(BoundingBox::parse('1,2,3'))->toBeNull()
        ->and(BoundingBox::parse('1,50,3,40'))->toBeNull();
});

it('aggregates points into grid cells', function () {
    $cells = GridAggregator::aggregate([
        ['lat' => -6.21, 'lng' => 106.81, 'score' => 80],
        ['lat' => -6.22, 'lng' => 106.82, 'score' => 40],
        ['lat' => 1.0, 'lng' => 100.0, 'score' => 10],
    ], 0.5);

    expect($cells)->toHaveCount(2);
    $jakarta = collect($cells)->firstWhere('count', 2);
    expect($jakarta['avg_score'])->toBe(60.0)->and($jakarta['hot'])->toBe(1);
});
