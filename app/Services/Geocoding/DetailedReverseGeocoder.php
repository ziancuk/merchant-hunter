<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

/** Reverse geocoder that also returns the city, so prospects.city can be overridden (D-70). */
interface DetailedReverseGeocoder
{
    /** @return array{address: string, city: ?string}|null */
    public function reverseDetailed(float $lat, float $lng): ?array;
}
