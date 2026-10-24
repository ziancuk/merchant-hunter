<?php

declare(strict_types=1);

namespace App\Services\Providers\Osm;

use App\DTO\BusinessDTO;
use App\Models\Category;
use Carbon\CarbonImmutable;

/** Maps Overpass elements to BusinessDTOs (tag → category via categories.osm_filters, lifecycle detection). */
final class OverpassMapper
{
    private const KEEP_TAGS = [
        'name', 'brand', 'operator', 'amenity', 'shop', 'tourism', 'craft', 'cuisine', 'opening_hours',
        'start_date', 'phone', 'website', 'addr:street', 'addr:housenumber', 'addr:city', 'addr:suburb',
        'addr:postcode', 'addr:full',
    ];

    /**
     * @param  array<string, mixed>  $response  decoded Overpass JSON
     * @param  list<Category>  $categories
     * @return list<BusinessDTO>
     */
    public static function map(array $response, array $categories, ?CarbonImmutable $fetchedAt = null): array
    {
        $fetchedAt ??= CarbonImmutable::now();
        $out = [];
        $seen = [];
        foreach ($response['elements'] ?? [] as $el) {
            if (! is_array($el)) {
                continue;
            }
            $dto = self::mapElement($el, $categories, $fetchedAt);
            if ($dto !== null && ! isset($seen[$dto->placeId])) {
                $seen[$dto->placeId] = true;
                $out[] = $dto;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $el
     * @param  list<Category>  $categories
     */
    public static function mapElement(array $el, array $categories, CarbonImmutable $fetchedAt): ?BusinessDTO
    {
        $type = $el['type'] ?? null;
        $id = $el['id'] ?? null;
        $tags = is_array($el['tags'] ?? null) ? $el['tags'] : [];
        if (! in_array($type, ['node', 'way', 'relation'], true) || $id === null) {
            return null;
        }
        $lat = $el['lat'] ?? $el['center']['lat'] ?? null;
        $lng = $el['lon'] ?? $el['center']['lon'] ?? null;
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $name = self::firstTag($tags, ['name', 'was:name', 'old_name', 'disused:name', 'abandoned:name', 'brand', 'operator']);
        if ($name === null) {
            return null;
        }

        [$categoryKey, $closed] = self::classify($tags, $categories);
        if ($categoryKey === null) {
            return null;
        }

        return new BusinessDTO(
            provider: 'osm',
            placeId: $type.'/'.$id,
            name: mb_substr($name, 0, 200),
            categoryKey: $categoryKey,
            lat: (float) $lat,
            lng: (float) $lng,
            address: self::address($tags),
            phone: self::phone($tags),
            website: self::website($tags),
            openingHours: self::firstTag($tags, ['opening_hours'], 255),
            isClosedPermanently: $closed,
            rawTags: self::rawTags($tags),
            fetchedAt: $fetchedAt,
        );
    }

    /**
     * @param  array<string, mixed>  $tags
     * @param  list<Category>  $categories
     * @return array{0: ?string, 1: bool} category key and whether a lifecycle tag marks it closed
     */
    private static function classify(array $tags, array $categories): array
    {
        $lifecycle = null;
        foreach ($categories as $category) {
            foreach ((array) $category->osm_filters as $key => $values) {
                $values = (array) $values;
                if (isset($tags[$key]) && in_array($tags[$key], $values, true)) {
                    return [$category->key, false];
                }
                if ($lifecycle === null) {
                    foreach (OverpassQuery::LIFECYCLE_PREFIXES as $prefix) {
                        $v = $tags[$prefix.':'.$key] ?? null;
                        if ($v !== null && in_array($v, $values, true)) {
                            $lifecycle = $category->key;
                            break;
                        }
                    }
                }
            }
        }

        return $lifecycle !== null ? [$lifecycle, true] : [null, false];
    }

    /**
     * @param  array<string, mixed>  $tags
     * @param  list<string>  $keys
     */
    private static function firstTag(array $tags, array $keys, int $max = 200): ?string
    {
        foreach ($keys as $k) {
            $v = isset($tags[$k]) && is_scalar($tags[$k]) ? trim((string) $tags[$k]) : '';
            if ($v !== '') {
                return mb_substr($v, 0, $max);
            }
        }

        return null;
    }

    /** @param array<string, mixed> $tags */
    private static function phone(array $tags): ?string
    {
        $raw = self::firstTag($tags, ['phone', 'contact:phone', 'contact:mobile', 'mobile']);
        if ($raw === null) {
            return null;
        }
        $first = trim(explode(';', $raw)[0]);

        return $first === '' ? null : mb_substr($first, 0, 40);
    }

    /** @param array<string, mixed> $tags */
    private static function website(array $tags): ?string
    {
        $raw = self::firstTag($tags, ['website', 'contact:website', 'url'], 255);
        if ($raw === null) {
            return null;
        }
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $raw) === 1) {
            // Only http(s) links are kept; javascript:, data: etc. are dropped.
            return preg_match('#^https?://#i', $raw) === 1 ? $raw : null;
        }

        return mb_substr('https://'.ltrim($raw, '/'), 0, 255);
    }

    /** @param array<string, mixed> $tags */
    private static function address(array $tags): ?string
    {
        $full = self::firstTag($tags, ['addr:full'], 300);
        if ($full !== null) {
            return $full;
        }
        $street = self::firstTag($tags, ['addr:street']);
        if ($street === null) {
            return null;
        }
        $line = $street.(self::firstTag($tags, ['addr:housenumber']) !== null ? ' No. '.self::firstTag($tags, ['addr:housenumber']) : '');
        $parts = array_filter([
            $line,
            self::firstTag($tags, ['addr:subdistrict', 'addr:suburb', 'addr:village']),
            self::firstTag($tags, ['addr:district']),
            self::firstTag($tags, ['addr:city']),
            self::firstTag($tags, ['addr:postcode']),
        ]);

        return mb_substr(implode(', ', $parts), 0, 300);
    }

    /**
     * @param  array<string, mixed>  $tags
     * @return array<string, string>
     */
    private static function rawTags(array $tags): array
    {
        $out = [];
        foreach ($tags as $k => $v) {
            $isLifecycle = false;
            foreach (OverpassQuery::LIFECYCLE_PREFIXES as $prefix) {
                if (str_starts_with((string) $k, $prefix.':')) {
                    $isLifecycle = true;
                }
            }
            if ((in_array($k, self::KEEP_TAGS, true) || $isLifecycle) && is_scalar($v)) {
                $out[(string) $k] = mb_substr((string) $v, 0, 255);
            }
        }

        return $out;
    }
}
