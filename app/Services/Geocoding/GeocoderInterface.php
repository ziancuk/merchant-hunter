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

    /**
     * Street-level fallback for places OSM does not list by name: the best street match inside a city,
     * or null. The point is somewhere on the street, not the building.
     *
     * @return array{display_name: string, lat: float, lng: float}|null
     */
    public function forwardStreet(string $street, string $city): ?array;

    /**
     * Free-text fallback below street level. $kind 'landmark' accepts a named place (mall, campus, building);
     * 'area' accepts only an administrative area (kecamatan/kelurahan), pinned at its centre. The hit must mention
     * the city, so a same-named place elsewhere is not picked up.
     *
     * @param  'landmark'|'area'  $kind
     * @return array{display_name: string, lat: float, lng: float}|null
     */
    public function forwardPlace(string $text, string $city, string $kind): ?array;
}
