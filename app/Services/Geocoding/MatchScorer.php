<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use App\Services\Dedupe\Similarity;
use App\Services\Geo\Haversine;

/** Forward-geocoding match score 0..100 (D-45) and its decision thresholds. */
final class MatchScorer
{
    public const AUTO_ACCEPT = 80;

    public const NEEDS_REVIEW = 50;

    private const BUSINESS_CLASSES = ['amenity', 'shop', 'office', 'tourism', 'craft', 'leisure', 'building', 'healthcare'];

    /**
     * score = 0.5 name similarity + 0.3 distance score (1 - km/20) + 0.1 type fit + 0.1 importance.
     *
     * @param  array<string, mixed>  $result  one Nominatim jsonv2 search result
     */
    public static function score(string $queryName, array $result, ?float $nearLat = null, ?float $nearLng = null): int
    {
        $candidateName = (string) ($result['name'] ?? '');
        if ($candidateName === '') {
            $candidateName = trim(explode(',', (string) ($result['display_name'] ?? ''))[0]);
        }
        $name = Similarity::names($queryName, $candidateName);

        $distance = 0.5; // unknown reference point: neutral
        if ($nearLat !== null && $nearLng !== null && isset($result['lat'], $result['lon'])) {
            $km = Haversine::distance($nearLat, $nearLng, (float) $result['lat'], (float) $result['lon']) / 1000;
            $distance = max(0.0, 1 - $km / 20);
        }

        $class = (string) ($result['category'] ?? $result['class'] ?? '');
        $type = in_array($class, self::BUSINESS_CLASSES, true) ? 1.0 : ($class === '' ? 0.5 : 0.2);

        $importance = max(0.0, min(1.0, (float) ($result['importance'] ?? 0.0)));

        return (int) round(100 * (0.5 * $name + 0.3 * $distance + 0.1 * $type + 0.1 * $importance));
    }

    /** @return 'ok'|'needs_review'|'failed' as outlets.geocode_status */
    public static function classify(int $score): string
    {
        return match (true) {
            $score >= self::AUTO_ACCEPT => 'ok',
            $score >= self::NEEDS_REVIEW => 'needs_review',
            default => 'failed',
        };
    }
}
