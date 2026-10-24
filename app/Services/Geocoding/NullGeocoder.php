<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

/** Placeholder bound in M2.0; replaced by NominatimGeocoder by BE-Provider-Scheduler. */
final class NullGeocoder implements GeocoderInterface
{
    public function reverse(float $lat, float $lng): ?string
    {
        return null;
    }

    public function forward(string $name, string $city, ?float $nearLat = null, ?float $nearLng = null): array
    {
        return [];
    }
}
