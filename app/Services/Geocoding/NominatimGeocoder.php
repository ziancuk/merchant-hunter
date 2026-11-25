<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Nominatim (D-40..D-45): reverse (jsonv2, zoom 18, Indonesian) and forward (countrycodes=id, limit 5,
 * results must name the outlet's city, MatchScorer). Shares one 4 req/min limit; stops on 403/429; feature flag
 * `hunter.geocoding.enabled`.
 */
class NominatimGeocoder implements DetailedReverseGeocoder, GeocoderInterface
{
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

        // No viewbox: the reference point is only a province centre, so a box around it missed most outlets.
        // The city is the boundary instead: a hit that does not name it is some other branch or shop.
        $bare = self::bareCity($city);
        $out = [];
        foreach ($this->get('/search', $query) as $r) {
            if (! is_array($r) || ! isset($r['lat'], $r['lon'])) {
                continue;
            }
            $display = (string) ($r['display_name'] ?? '');
            $inCity = $bare === '' ? null : mb_stripos($display, $bare) !== false;
            if ($inCity === false) {
                continue;
            }
            $out[] = [
                'display_name' => mb_substr($display, 0, 300),
                'lat' => (float) $r['lat'],
                'lng' => (float) $r['lon'],
                'score' => MatchScorer::score($name, $r, $nearLat, $nearLng, $inCity),
            ];
        }
        usort($out, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $out;
    }

    public function forwardStreet(string $street, string $city): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        $city = self::bareCity($city);
        $results = $this->get('/search', [
            'q' => str_replace('.', '', $street).', '.$city, 'format' => 'jsonv2', 'limit' => 5, 'countrycodes' => 'id', 'accept-language' => 'id',
        ]);
        $words = self::streetWords($street);
        foreach ($results as $r) {
            if (! is_array($r) || ! isset($r['lat'], $r['lon'])) {
                continue;
            }
            $category = (string) ($r['category'] ?? $r['class'] ?? '');
            $display = (string) ($r['display_name'] ?? '');
            // A real street, or something named after it (OSM often returns the MRT line or a bridge of "Jalan X"). Never a
            // city/district polygon: its centroid is no better than the city we already know.
            $onStreet = $category === 'highway' || (
                ! in_array($category, ['boundary', 'place'], true)
                && ($city === '' || mb_stripos($display, $city) !== false)
                && self::mentionsAll($display, $words)
            );
            if ($onStreet) {
                return ['display_name' => mb_substr($display, 0, 300), 'lat' => (float) $r['lat'], 'lng' => (float) $r['lon']];
            }
        }

        return null;
    }

    /** @return list<string> the distinctive words of "Jalan RS. Fatmawati Raya" => ['rs', 'fatmawati'] */
    private static function streetWords(string $street): array
    {
        $words = preg_split('/\s+/u', mb_strtolower(str_replace('.', '', $street))) ?: [];

        return array_values(array_filter($words, fn (string $w) => mb_strlen($w) >= 2 && ! in_array($w, ['jalan', 'gang', 'raya'], true)));
    }

    /** @param list<string> $words */
    private static function mentionsAll(string $display, array $words): bool
    {
        $display = mb_strtolower(str_replace('.', '', $display));

        return $words !== [] && array_reduce($words, fn (bool $all, string $w) => $all && str_contains($display, $w), true);
    }

    public function forwardPlace(string $text, string $city, string $kind): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        $city = self::bareCity($city);
        $results = $this->get('/search', [
            'q' => $text.', '.$city, 'format' => 'jsonv2', 'limit' => 5, 'countrycodes' => 'id', 'accept-language' => 'id',
        ]);
        foreach ($results as $r) {
            if (! is_array($r) || ! isset($r['lat'], $r['lon'])) {
                continue;
            }
            $category = (string) ($r['category'] ?? $r['class'] ?? '');
            $isArea = in_array($category, ['boundary', 'place'], true);
            $display = (string) ($r['display_name'] ?? '');
            if ($category === 'highway' || $isArea !== ($kind === 'area') || ($city !== '' && mb_stripos($display, $city) === false)) {
                continue;
            }
            // The hit has to carry the place's own first word, not just sit in the same city.
            if ($kind === 'landmark' && mb_stripos($display, (string) strtok($text, ' ')) === false) {
                continue;
            }

            return ['display_name' => mb_substr($display, 0, 300), 'lat' => (float) $r['lat'], 'lng' => (float) $r['lon']];
        }

        return null;
    }

    /** "KOTA JAKARTA PUSAT" => "JAKARTA PUSAT": the form OSM display names use. */
    private static function bareCity(string $city): string
    {
        return trim((string) preg_replace('/\b(KOTA|KABUPATEN|KAB\.?)\s+/i', '', $city));
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
