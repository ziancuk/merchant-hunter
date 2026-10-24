<?php

declare(strict_types=1);

namespace App\Services\Geo;

final class BoundingBox
{
    public function __construct(
        public readonly float $west,
        public readonly float $south,
        public readonly float $east,
        public readonly float $north,
    ) {}

    /** Box that fully contains a circle of $radiusM metres around the centre. */
    public static function around(float $lat, float $lng, float $radiusM): self
    {
        $dLat = rad2deg($radiusM / Haversine::EARTH_RADIUS_M);
        $cos = max(0.01, cos(deg2rad($lat)));
        $dLng = rad2deg($radiusM / (Haversine::EARTH_RADIUS_M * $cos));

        return new self($lng - $dLng, $lat - $dLat, $lng + $dLng, $lat + $dLat);
    }

    /** Parse "west,south,east,north"; null when malformed. */
    public static function parse(string $value): ?self
    {
        $parts = array_map('trim', explode(',', $value));
        if (count($parts) !== 4 || count(array_filter($parts, 'is_numeric')) !== 4) {
            return null;
        }
        [$w, $s, $e, $n] = array_map('floatval', $parts);
        if ($s > $n || $s < -90 || $n > 90) {
            return null;
        }

        return new self($w, $s, $e, $n);
    }

    public function contains(float $lat, float $lng): bool
    {
        return $lat >= $this->south && $lat <= $this->north && $lng >= $this->west && $lng <= $this->east;
    }
}
