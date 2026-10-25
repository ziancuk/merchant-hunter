<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\Services\Geo\Haversine;

/** Visit-route planner: nearest-neighbour seed improved with 2-opt on straight-line (haversine) distance. */
final class RoutePlanner
{
    public function __construct(private readonly GoogleMapsUrlBuilder $urls = new GoogleMapsUrlBuilder) {}

    /**
     * @param  list<array{id: int, name: string, lat: float, lng: float}>  $stops  at least 2
     * @param  array{lat: float, lng: float}|null  $start  defaults to the first stop
     * @return array{order: list<int>, stops: list<array<string, mixed>>, total_km: float, estimate_note: string, path: list<array{float, float}>, google_maps_urls: list<string>}
     */
    public function plan(array $stops, ?array $start = null): array
    {
        $stops = array_values($stops);

        // Node 0 is the fixed origin: the explicit start, or else the first stop.
        $nodes = $start !== null
            ? [['id' => null, 'name' => 'Start', 'lat' => $start['lat'], 'lng' => $start['lng']], ...$stops]
            : $stops;

        $path = $this->twoOpt($nodes, $this->nearestNeighbour($nodes));

        $ordered = array_map(fn (int $i) => $nodes[$i], $path);
        $legs = $this->legs($ordered);

        $outStops = [];
        $order = [];
        foreach ($ordered as $idx => $node) {
            if ($node['id'] === null) {
                continue;
            }
            $order[] = $node['id'];
            $outStops[] = [
                'id' => $node['id'],
                'name' => $node['name'],
                'lat' => $node['lat'],
                'lng' => $node['lng'],
                'leg_km' => round($legs[$idx] / 1000, 2),
            ];
        }

        return [
            'order' => $order,
            'stops' => $outStops,
            'total_km' => round(array_sum($legs) / 1000, 2),
            'estimate_note' => __('api.route_estimate_note'),
            'path' => array_map(fn (array $n) => [$n['lng'], $n['lat']], $ordered),
            'google_maps_urls' => $this->urls->build(array_map(fn (array $n) => ['lat' => $n['lat'], 'lng' => $n['lng']], $ordered)),
        ];
    }

    /**
     * Greedy nearest-neighbour visiting order starting at node 0.
     *
     * @param  list<array{lat: float, lng: float}>  $nodes
     * @return list<int>
     */
    public function nearestNeighbour(array $nodes): array
    {
        $n = count($nodes);
        $path = [0];
        $left = $n > 1 ? range(1, $n - 1) : [];
        $current = 0;
        while ($left !== []) {
            $best = null;
            $bestD = INF;
            foreach ($left as $k => $candidate) {
                $d = $this->d($nodes, $current, $candidate);
                if ($d < $bestD) {
                    $bestD = $d;
                    $best = $k;
                }
            }
            $current = $left[$best];
            $path[] = $current;
            array_splice($left, $best, 1);
        }

        return $path;
    }

    /**
     * 2-opt on an open path with a fixed first node; only strictly improving swaps are applied,
     * so the result is never longer than the input.
     *
     * @param  list<array{lat: float, lng: float}>  $nodes
     * @param  list<int>  $path
     * @return list<int>
     */
    public function twoOpt(array $nodes, array $path): array
    {
        $n = count($path);
        $improved = true;
        $passes = 0;
        while ($improved && $passes++ < 100) {
            $improved = false;
            for ($i = 1; $i < $n - 1; $i++) {
                for ($k = $i + 1; $k < $n; $k++) {
                    $before = $this->d($nodes, $path[$i - 1], $path[$i]);
                    $after = $this->d($nodes, $path[$i - 1], $path[$k]);
                    if ($k + 1 < $n) {
                        $before += $this->d($nodes, $path[$k], $path[$k + 1]);
                        $after += $this->d($nodes, $path[$i], $path[$k + 1]);
                    }
                    if ($after < $before - 1e-6) {
                        $path = [...array_slice($path, 0, $i), ...array_reverse(array_slice($path, $i, $k - $i + 1)), ...array_slice($path, $k + 1)];
                        $improved = true;
                    }
                }
            }
        }

        return $path;
    }

    /**
     * Total path length in metres.
     *
     * @param  list<array{lat: float, lng: float}>  $nodes
     * @param  list<int>  $path
     */
    public function length(array $nodes, array $path): float
    {
        $sum = 0.0;
        for ($i = 1, $c = count($path); $i < $c; $i++) {
            $sum += $this->d($nodes, $path[$i - 1], $path[$i]);
        }

        return $sum;
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $ordered
     * @return list<float> leg length (m) arriving at each node; 0 for the first
     */
    private function legs(array $ordered): array
    {
        $legs = [0.0];
        for ($i = 1, $c = count($ordered); $i < $c; $i++) {
            $legs[] = Haversine::distance($ordered[$i - 1]['lat'], $ordered[$i - 1]['lng'], $ordered[$i]['lat'], $ordered[$i]['lng']);
        }

        return $legs;
    }

    /** @param list<array{lat: float, lng: float}> $nodes */
    private function d(array $nodes, int $a, int $b): float
    {
        return Haversine::distance($nodes[$a]['lat'], $nodes[$a]['lng'], $nodes[$b]['lat'], $nodes[$b]['lng']);
    }
}
