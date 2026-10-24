<?php

use App\Services\Geo\Haversine;
use App\Services\Routing\GoogleMapsUrlBuilder;
use App\Services\Routing\RoutePlanner;

function routeStops(int $n, int $seed = 1): array
{
    mt_srand($seed);
    $stops = [];
    for ($i = 1; $i <= $n; $i++) {
        $stops[] = ['id' => $i, 'name' => "S{$i}", 'lat' => -6.2 + mt_rand(-300, 300) / 10000, 'lng' => 106.8 + mt_rand(-300, 300) / 10000];
    }

    return $stops;
}

it('visits every stop exactly once and keeps the first stop as origin', function () {
    $stops = routeStops(12);
    $plan = (new RoutePlanner)->plan($stops);

    expect($plan['order'])->toHaveCount(12)
        ->and($plan['order'][0])->toBe(1)
        ->and(collect($plan['order'])->sort()->values()->all())->toBe(range(1, 12))
        ->and($plan['stops'][0]['leg_km'])->toBe(0.0)
        ->and($plan['estimate_note'])->toContain('garis lurus')
        ->and($plan['path'])->toHaveCount(12);
});

it('never produces a longer route than nearest-neighbour (2-opt only improves)', function (int $seed) {
    $stops = routeStops(20, $seed);
    $planner = new RoutePlanner;

    $nn = $planner->nearestNeighbour($stops);
    $opt = $planner->twoOpt($stops, $nn);

    expect($planner->length($stops, $opt))->toBeLessThanOrEqual($planner->length($stops, $nn) + 1e-6)
        ->and($opt[0])->toBe(0)
        ->and(collect($opt)->sort()->values()->all())->toBe(range(0, 19));
})->with([1, 2, 3, 4, 5, 6, 7, 8]);

it('improves a deliberately bad order', function () {
    // points on a line; the bad path zig-zags
    $nodes = [];
    foreach ([0, 4, 1, 3, 2] as $x) {
        $nodes[] = ['lat' => 0.0, 'lng' => 106.0 + $x * 0.01];
    }
    $planner = new RoutePlanner;
    $bad = [0, 1, 2, 3, 4];

    expect($planner->length($nodes, $planner->twoOpt($nodes, $bad)))->toBeLessThan($planner->length($nodes, $bad));
});

it('uses an explicit start without listing it as a stop', function () {
    $stops = [
        ['id' => 1, 'name' => 'A', 'lat' => -6.20, 'lng' => 106.80],
        ['id' => 2, 'name' => 'B', 'lat' => -6.30, 'lng' => 106.90],
    ];
    $plan = (new RoutePlanner)->plan($stops, ['lat' => -6.31, 'lng' => 106.91]);

    expect($plan['order'])->toBe([2, 1])
        ->and($plan['stops'][0]['leg_km'])->toBeGreaterThan(0.0)
        ->and($plan['path'])->toHaveCount(3)
        ->and($plan['path'][0])->toBe([106.91, -6.31]);
});

it('reports the total distance as the sum of legs', function () {
    $plan = (new RoutePlanner)->plan(routeStops(6));
    $sum = array_sum(array_column($plan['stops'], 'leg_km'));

    expect($plan['total_km'])->toEqualWithDelta($sum, 0.05);
});

it('measures realistic haversine distances', function () {
    $stops = [
        ['id' => 1, 'name' => 'A', 'lat' => 0.0, 'lng' => 100.0],
        ['id' => 2, 'name' => 'B', 'lat' => 0.0, 'lng' => 101.0],
    ];

    expect((new RoutePlanner)->plan($stops)['total_km'])->toEqualWithDelta(Haversine::distance(0, 100, 0, 101) / 1000, 0.01);
});

it('builds a single Google Maps URL for up to 9 waypoints', function () {
    $points = array_map(fn ($i) => ['lat' => -6.2 + $i / 100, 'lng' => 106.8], range(0, 10)); // origin + 9 waypoints + destination
    $urls = (new GoogleMapsUrlBuilder)->build($points);

    expect($urls)->toHaveCount(1)
        ->and($urls[0])->toStartWith('https://www.google.com/maps/dir/?api=1&origin=-6.2,106.8&destination=-6.1,106.8&waypoints=')
        ->and(substr_count($urls[0], '%7C'))->toBe(8); // 9 waypoints => 8 separators
});

it('splits routes with more than 9 waypoints into several URLs sharing endpoints', function () {
    $points = array_map(fn ($i) => ['lat' => -6.2 + $i / 100, 'lng' => 106.8], range(0, 20)); // 21 points
    $urls = (new GoogleMapsUrlBuilder)->build($points);

    expect($urls)->toHaveCount(2)
        ->and($urls[0])->toContain('destination=-6.1,106.8')
        ->and($urls[1])->toContain('origin=-6.1,106.8')
        ->and($urls[1])->toContain('destination=-6,106.8');
    foreach ($urls as $url) {
        $waypoints = preg_match('/waypoints=([^&]+)/', $url, $m) ? explode('%7C', $m[1]) : [];
        expect(count($waypoints))->toBeLessThanOrEqual(9);
    }
});

it('builds no URL for fewer than two points and a bare URL for exactly two', function () {
    $builder = new GoogleMapsUrlBuilder;

    expect($builder->build([['lat' => 1.0, 'lng' => 2.0]]))->toBe([])
        ->and($builder->build([['lat' => 1.0, 'lng' => 2.0], ['lat' => 3.0, 'lng' => 4.0]]))
        ->toBe(['https://www.google.com/maps/dir/?api=1&origin=1,2&destination=3,4']);
});
