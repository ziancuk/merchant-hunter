<?php

declare(strict_types=1);

namespace App\Services\Providers\Osm;

use App\Models\Category;

/** Builds the single per-outlet Overpass query of D-30. */
final class OverpassQuery
{
    /** OSM lifecycle prefixes (D-35). */
    public const LIFECYCLE_PREFIXES = ['disused', 'abandoned', 'was', 'removed', 'demolished'];

    /**
     * @param  list<Category>  $categories
     */
    public static function build(float $lat, float $lng, int $radiusMeters, array $categories): string
    {
        $around = sprintf('(around:%d,%.5f,%.5f)', $radiusMeters, $lat, $lng);
        $lines = [];
        foreach (self::mergedFilters($categories) as $key => $values) {
            $regex = '^('.implode('|', $values).')$';
            $lines[] = sprintf('  nwr["%s"~"%s"]%s;', $key, $regex, $around);
            foreach (self::LIFECYCLE_PREFIXES as $prefix) {
                $lines[] = sprintf('  nwr["%s:%s"~"%s"]%s;', $prefix, $key, $regex, $around);
            }
        }

        return "[out:json][timeout:60][maxsize:67108864];\n(\n".implode("\n", $lines)."\n);\nout center tags;";
    }

    /**
     * Union of every category's filters per tag key.
     *
     * @param  list<Category>  $categories
     * @return array<string, list<string>>
     */
    public static function mergedFilters(array $categories): array
    {
        $merged = [];
        foreach ($categories as $category) {
            foreach ((array) $category->osm_filters as $key => $values) {
                if (! preg_match('/^[a-z_]+$/', (string) $key)) {
                    continue;
                }
                foreach ((array) $values as $value) {
                    // Only plain OSM tag values are interpolated into the query (no regex/quote injection).
                    if (is_string($value) && preg_match('/^[A-Za-z0-9_]+$/', $value)) {
                        $merged[$key][$value] = $value;
                    }
                }
            }
        }

        return array_map('array_values', $merged);
    }
}
