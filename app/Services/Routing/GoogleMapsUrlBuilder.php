<?php

declare(strict_types=1);

namespace App\Services\Routing;

/** Builds https://www.google.com/maps/dir links; at most 9 waypoints per URL (D-56), split for more. */
final class GoogleMapsUrlBuilder
{
    public const MAX_WAYPOINTS = 9;

    /**
     * @param  list<array{lat: float, lng: float}>  $points  ordered: origin, stops…, destination
     * @return list<string>
     */
    public function build(array $points): array
    {
        $points = array_values($points);
        $count = count($points);
        if ($count < 2) {
            return [];
        }

        $urls = [];
        $i = 0;
        while ($i < $count - 1) {
            $end = min($i + self::MAX_WAYPOINTS + 1, $count - 1);
            $url = 'https://www.google.com/maps/dir/?api=1&origin='.self::coord($points[$i])
                .'&destination='.self::coord($points[$end]);
            $waypoints = array_map(fn (array $p) => self::coord($p), array_slice($points, $i + 1, $end - $i - 1));
            if ($waypoints !== []) {
                $url .= '&waypoints='.implode('%7C', $waypoints);
            }
            $urls[] = $url;
            $i = $end;
        }

        return $urls;
    }

    /** @param array{lat: float, lng: float} $p */
    private static function coord(array $p): string
    {
        return self::num($p['lat']).','.self::num($p['lng']);
    }

    private static function num(float $v): string
    {
        $s = rtrim(rtrim(sprintf('%.6f', $v), '0'), '.');

        return $s === '-0' || $s === '' ? '0' : $s;
    }
}
