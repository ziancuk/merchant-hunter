<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

interface GeocoderInterface
{
    /** One-line address for a coordinate, or null when none is found. */
    public function reverse(float $lat, float $lng): ?string;

    /**
     * Forward geocoding candidates, best first.
     *
     * @return list<array{display_name: string, lat: float, lng: float, score: int}>
     */
    public function forward(string $name, string $city, ?float $nearLat = null, ?float $nearLng = null): array;
}
