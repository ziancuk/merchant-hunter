<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use App\Services\Geo\BoundingBox;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Nominatim (D-40..D-45): reverse (jsonv2, zoom 18, Indonesian) and forward (countrycodes=id, limit 5,
 * bounded viewbox, MatchScorer). Shares one 4 req/min limit; stops on 403/429; feature flag
 * `hunter.geocoding.enabled`.
 */
class NominatimGeocoder implements DetailedReverseGeocoder, GeocoderInterface
{
    /** Half-width of the forward-search viewbox around the reference point. */
    public const VIEWBOX_RADIUS_M = 25_000;

    public function __construct(private readonly NominatimRateLimiter $limiter) {}

    public function enabled(): bool
    {
        return (bool) config('hunter.geocoding.enabled', true);
    }

    public function reverse(float $lat, float $lng): ?string
    {
        return $this->reverseDetailed($lat, $lng)['address'] ?? null;
    }

    public function reverseDetailed(float $lat, float $lng): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        $json = $this->get('/reverse', [
            'lat' => $lat, 'lon' => $lng, 'format' => 'jsonv2', 'zoom' => 18, 'addressdetails' => 1, 'accept-language' => 'id',
        ]);
        if (isset($json['error'])) {
            return null;
        }
        $address = AddressFormatter::format($json);

        return $address === null ? null : ['address' => $address, 'city' => AddressFormatter::city($json)];
    }

    public function forward(string $name, string $city, ?float $nearLat = null, ?float $nearLng = null): array
    {
        if (! $this->enabled()) {
            return [];
        }
        $query = [
            'q' => trim($name.', '.$city), 'format' => 'jsonv2', 'addressdetails' => 1, 'limit' => 5,
            'countrycodes' => 'id', 'accept-language' => 'id',
        ];
        if ($nearLat !== null && $nearLng !== null) {
            $box = BoundingBox::around($nearLat, $nearLng, self::VIEWBOX_RADIUS_M);
            $query['viewbox'] = sprintf('%.6f,%.6f,%.6f,%.6f', $box->west, $box->north, $box->east, $box->south);
            $query['bounded'] = 1;
        }

        $results = $this->get('/search', $query);
        $out = [];
        foreach ($results as $r) {
            if (! is_array($r) || ! isset($r['lat'], $r['lon'])) {
                continue;
            }
            $out[] = [
                'display_name' => mb_substr((string) ($r['display_name'] ?? ''), 0, 300),
                'lat' => (float) $r['lat'],
                'lng' => (float) $r['lon'],
                'score' => MatchScorer::score($name, $r, $nearLat, $nearLng),
            ];
        }
        usort($out, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $out;
    }

    public function userAgent(): string
    {
        return 'pos-merchant-hunter/1.0 ('.config('hunter.overpass.contact_email').')';
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int|string, mixed>
     *
     * @throws GeocodingException
     */
    private function get(string $path, array $query): array
    {
        if ($this->limiter->blocked()) {
            throw new GeocodingBlockedException('Nominatim blocked us earlier (403/429); geocoding is paused');
        }
        $this->limiter->acquire();

        try {
            $response = Http::withUserAgent($this->userAgent())->timeout(20)->acceptJson()
                ->get(rtrim((string) config('hunter.geocoding.nominatim_url'), '/').$path, $query);
        } catch (ConnectionException $e) {
            throw new GeocodingException('Nominatim connection failed: '.$e->getMessage(), 0, $e);
        }

        return $this->decode($response);
    }

    /** @return array<int|string, mixed> */
    private function decode(Response $response): array
    {
        if (in_array($response->status(), [403, 429], true)) {
            $this->limiter->block();

            throw new GeocodingBlockedException('Nominatim answered HTTP '.$response->status().'; geocoding stopped');
        }
        if (! $response->successful()) {
            throw new GeocodingException('Nominatim answered HTTP '.$response->status());
        }
        $json = json_decode($response->body(), true);
        if (! is_array($json)) {
            throw new GeocodingException('Nominatim returned invalid JSON');
        }

        return $json;
    }
}
