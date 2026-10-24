<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

/** One-line Indonesian address from a Nominatim jsonv2 response (D-43). */
final class AddressFormatter
{
    /**
     * @param  array<string, mixed>  $response  decoded reverse-geocoding JSON
     */
    public static function format(array $response): ?string
    {
        $a = is_array($response['address'] ?? null) ? $response['address'] : [];

        $road = self::pick($a, ['road', 'pedestrian', 'footway', 'residential']);
        $number = self::pick($a, ['house_number']);
        $street = $road !== null ? trim($road.($number !== null ? ' No. '.$number : '')) : null;

        $parts = [
            $street,
            self::pick($a, ['neighbourhood', 'suburb', 'village', 'quarter']),
            self::pick($a, ['city_district', 'county', 'municipality']),
            self::pick($a, ['city', 'town', 'regency']),
            self::pick($a, ['state']),
            self::pick($a, ['postcode']),
        ];

        $seen = [];
        $out = [];
        foreach ($parts as $part) {
            if ($part === null) {
                continue;
            }
            $key = mb_strtolower($part);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $part;
        }

        if ($out === []) {
            // Raw display_name as the fallback (D-43).
            $display = trim((string) ($response['display_name'] ?? ''));

            return $display === '' ? null : mb_substr($display, 0, 300);
        }

        return mb_substr(implode(', ', $out), 0, 300);
    }

    /** @param array<string, mixed> $response */
    public static function city(array $response): ?string
    {
        $a = is_array($response['address'] ?? null) ? $response['address'] : [];
        $city = self::pick($a, ['city', 'town', 'regency', 'municipality', 'county']);

        return $city !== null ? mb_substr($city, 0, 80) : null;
    }

    /**
     * @param  array<string, mixed>  $address
     * @param  list<string>  $keys
     */
    private static function pick(array $address, array $keys): ?string
    {
        foreach ($keys as $k) {
            $v = $address[$k] ?? null;
            if (is_scalar($v) && trim((string) $v) !== '') {
                return trim((string) $v);
            }
        }

        return null;
    }
}
