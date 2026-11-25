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
        $near = [
            $province?->lat !== null ? (float) $province->lat : null,
            $province?->lng !== null ? (float) $province->lng : null,
        ];
        $candidates = $this->geocoder->forward((string) $outlet->name, (string) $outlet->city, ...$near);
        // Nothing usable under the full name: retry without branch words and the city ("X Bensu Palembang" => "X Bensu").
        $loose = self::looseName((string) $outlet->name, (string) $outlet->city);
        if ($loose !== null && ($candidates === [] || MatchScorer::classify((int) $candidates[0]['score']) === 'failed')) {
            $candidates = $this->geocoder->forward($loose, (string) $outlet->city, ...$near) ?: $candidates;
        }
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

        if ($status === 'failed' && $this->placeFromAddress($outlet)) {
            return 'ok';
        }

        return $status;
    }

    /**
     * Fallback for outlets OSM does not know by name, using the address: street, then a landmark named in it
     * (mall, campus, building), then the centre of its kecamatan/kelurahan. Stored as ok, but geocode_source
     * 'nominatim_street' | 'nominatim_landmark' | 'nominatim_area' lets the UI flag the pin as approximate.
     * Leaves the outlet untouched on a miss.
     */
    private function placeFromAddress(Outlet $outlet): bool
    {
        $city = (string) $outlet->city;
        $street = self::street($outlet->address);
        $landmark = $street === null ? self::landmark($outlet->address) : null;
        $area = self::area($outlet->address);

        $tiers = [
            ['nominatim_street', $street !== null ? fn () => $this->geocoder->forwardStreet($street, $city) : null],
            ['nominatim_landmark', $landmark !== null ? fn () => $this->geocoder->forwardPlace($landmark, $city, 'landmark') : null],
            ['nominatim_area', $area !== null ? fn () => $this->geocoder->forwardPlace($area, $city, 'area') : null],
        ];
        foreach ($tiers as [$source, $search]) {
            $hit = $search?->__invoke();
            if ($hit === null) {
                continue;
            }

            $outlet->lat = round($hit['lat'], 6);
            $outlet->lng = round($hit['lng'], 6);
            $outlet->geocode_source = $source;
            $outlet->geocode_status = GeocodeStatus::Ok;
            $outlet->geocode_score = null;
            $outlet->geocode_candidates = [$hit];
            $outlet->save();

            return true;
        }

        return false;
    }

    /** The name without branch words, city words and a trailing number; null when that leaves nothing new to search. */
    public static function looseName(string $name, string $city): ?string
    {
        $bare = (string) preg_replace('/\b(KOTA|KABUPATEN|KAB\.?)\s+/iu', '', $city);
        $cityWords = array_filter(preg_split('/\s+/u', mb_strtolower($bare)) ?: [], fn (string $w) => mb_strlen($w) >= 3);
        $drop = ['outlet', 'cabang', 'cab', 'branch', 'gerai', 'store'];

        $words = preg_split('/\s+/u', trim((string) preg_replace('/\(.*?\)/u', ' ', $name))) ?: [];
        $words = array_values(array_filter($words, fn (string $w) => $w !== '' && ! in_array(mb_strtolower($w), [...$drop, ...$cityWords], true)));
        while ($words !== [] && preg_match('/^\d+$/u', end($words)) === 1) {
            array_pop($words);
        }
        $loose = implode(' ', $words);

        return mb_strlen($loose) >= 5 && mb_strtolower($loose) !== mb_strtolower(trim($name)) ? $loose : null;
    }

    /** The first address segment that is a street ("Jl. Pramuka No. 5, Kec. X" => "Jalan Pramuka"; "Gedung A, Jl. B" => "Jalan B"), else null. */
    public static function street(?string $address): ?string
    {
        foreach (explode(',', (string) $address) as $segment) {
            $street = self::streetFrom(trim($segment));
            if ($street !== null) {
                return $street;
            }
        }

        return null;
    }

    private static function streetFrom(string $first): ?string
    {
        $first = (string) preg_replace('/\b(no|nomor|blok|rt|rw|kav|kavling|ruko|komplek|kompleks|lantai|lt)\b\.?.*$/iu', '', $first);
        // "Lingkaran1" => "Lingkaran 1"; "Tj." => "Tanjung".
        $first = (string) preg_replace('/(?<=\p{L})(?=\d)/u', ' ', $first);
        $first = (string) preg_replace(['/^(jl|jln)\.?\s*/iu', '/^gg\.?\s*/iu', '/\bjend(?:\.\s*|\s+)/iu', '/\btj\.\s*/iu', '/\s+/u'], ['Jalan ', 'Gang ', 'Jenderal ', 'Tanjung ', ' '], trim($first));
        $first = trim($first, " .");

        return preg_match('/^(jalan|gang)\s+\S/iu', $first) === 1 ? $first : null;
    }

    /** A named place at the start of an address that is not a street ("Kota Kasablanka Mall. Barang yang…" => "Kota Kasablanka Mall"). */
    public static function landmark(?string $address): ?string
    {
        if (self::street($address) !== null) {
            return null;
        }
        $first = trim(explode(',', (string) $address)[0]);
        $first = (string) preg_split('/\.\s/u', $first)[0];
        $first = trim((string) preg_replace('/\b(lantai|lt|blok|no|nomor|rt|rw|unit)\b\.?.*$/iu', '', $first), " .");

        // Only named places: a bare "Bandung" or a street fragment would match some unrelated shop of the same name.
        $isPlace = preg_match('/\b(mall|plaza|plasa|square|town|city|tower|menara|residence|residences|graha|wisma|gedung|building|universitas|fakultas|kampus|pasar|hotel|rumah sakit|stasiun|bandara|terminal|apartemen|center|centre|walk|park|point|place|market|station|hospital|school|sekolah)\b/iu', $first) === 1;

        return $isPlace && mb_strlen($first) >= 4 && mb_strlen($first) <= 60 && preg_match('/\b(jl|jalan|gang|gg)\b/iu', $first) !== 1 ? $first : null;
    }

    /** "Kec. Serpong Utara" / "Kelurahan Lidah Kulon" anywhere in the address, as "Kecamatan Serpong Utara"; else null. */
    public static function area(?string $address): ?string
    {
        preg_match_all('/\b(kec|kecamatan|kel|kelurahan)\b\.?\s+([a-z]+(?:\s[a-z]+)?)/iu', (string) $address, $all, PREG_SET_ORDER);
        foreach ($all as $m) {
            $name = trim((string) preg_replace('/\s+(kab|kabupaten|kota|jawa|jakarta|jl|jalan)\b.*$/iu', '', $m[2]));
            if (mb_strlen($name) >= 4 && ! in_array(mb_strtolower($name), ['jalan', 'kota', 'kabupaten'], true)) {
                return (in_array(mb_strtolower($m[1]), ['kel', 'kelurahan'], true) ? 'Kelurahan ' : 'Kecamatan ').$name;
            }
        }

        return null;
    }
}
