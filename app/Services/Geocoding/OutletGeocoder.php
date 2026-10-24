<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use App\Enums\GeocodeStatus;
use App\Models\Outlet;

/** Forward-geocodes an outlet and applies the D-45 thresholds (>=80 auto, 50-79 review, <50 failed). */
class OutletGeocoder
{
    public function __construct(private readonly GeocoderInterface $geocoder) {}

    public function available(): bool
    {
        return ! ($this->geocoder instanceof NullGeocoder) && (bool) config('hunter.geocoding.enabled', true);
    }

    /**
     * @return 'ok'|'needs_review'|'failed' the outlet's resulting geocode_status
     *
     * @throws GeocodingException when the geocoder is blocked or failing (the outlet is left untouched)
     */
    public function geocode(Outlet $outlet): string
    {
        $outlet->loadMissing('province');
        $province = $outlet->province;
        $candidates = $this->geocoder->forward(
            (string) $outlet->name,
            (string) $outlet->city,
            $province?->lat !== null ? (float) $province->lat : null,
            $province?->lng !== null ? (float) $province->lng : null,
        );
        $candidates = array_slice($candidates, 0, 5);
        $best = $candidates[0] ?? null;
        $score = $best['score'] ?? null;
        $status = $best === null ? 'failed' : MatchScorer::classify((int) $score);

        $outlet->geocode_source = 'nominatim';
        $outlet->geocode_score = $score;
        $outlet->geocode_candidates = $candidates;
        $outlet->geocode_status = GeocodeStatus::from($status);
        if ($status === 'ok' && $best !== null) {
            $outlet->lat = round($best['lat'], 6);
            $outlet->lng = round($best['lng'], 6);
        }
        $outlet->save();

        return $status;
    }
}
