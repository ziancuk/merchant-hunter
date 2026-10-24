<?php

declare(strict_types=1);

namespace App\Services\Dedupe;

/** Pure-PHP fuzzy similarity: average of Jaro-Winkler and token_set_ratio (TRD §5 point 4). */
final class Similarity
{
    /** Similarity (0..1) of two raw names; normalises both first. */
    public static function names(string $a, string $b): float
    {
        return self::normalized(NameNormalizer::normalize($a), NameNormalizer::normalize($b));
    }

    /** Similarity (0..1) of two already normalised names. */
    public static function normalized(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }

        return round((self::jaroWinkler($a, $b) + self::tokenSetRatio($a, $b)) / 2, 4);
    }

    public static function jaro(string $a, string $b): float
    {
        $s1 = mb_str_split($a);
        $s2 = mb_str_split($b);
        $l1 = count($s1);
        $l2 = count($s2);
        if ($l1 === 0 || $l2 === 0) {
            return 0.0;
        }
        $window = max(0, intdiv(max($l1, $l2), 2) - 1);
        $m1 = array_fill(0, $l1, false);
        $m2 = array_fill(0, $l2, false);
        $matches = 0;
        for ($i = 0; $i < $l1; $i++) {
            $from = max(0, $i - $window);
            $to = min($l2 - 1, $i + $window);
            for ($j = $from; $j <= $to; $j++) {
                if (! $m2[$j] && $s1[$i] === $s2[$j]) {
                    $m1[$i] = $m2[$j] = true;
                    $matches++;
                    break;
                }
            }
        }
        if ($matches === 0) {
            return 0.0;
        }
        $transpositions = 0;
        $k = 0;
        for ($i = 0; $i < $l1; $i++) {
            if ($m1[$i]) {
                while (! $m2[$k]) {
                    $k++;
                }
                if ($s1[$i] !== $s2[$k]) {
                    $transpositions++;
                }
                $k++;
            }
        }

        return ($matches / $l1 + $matches / $l2 + ($matches - $transpositions / 2) / $matches) / 3;
    }

    public static function jaroWinkler(string $a, string $b, float $p = 0.1): float
    {
        $jaro = self::jaro($a, $b);
        $s1 = mb_str_split($a);
        $s2 = mb_str_split($b);
        $prefix = 0;
        $max = min(4, count($s1), count($s2));
        while ($prefix < $max && $s1[$prefix] === $s2[$prefix]) {
            $prefix++;
        }

        return $jaro + $prefix * $p * (1 - $jaro);
    }

    /** token_set_ratio as in fuzzywuzzy, scaled 0..1. */
    public static function tokenSetRatio(string $a, string $b): float
    {
        $ta = array_values(array_unique(array_filter(explode(' ', $a), 'strlen')));
        $tb = array_values(array_unique(array_filter(explode(' ', $b), 'strlen')));
        if ($ta === [] || $tb === []) {
            return 0.0;
        }
        $inter = array_values(array_intersect($ta, $tb));
        $diffA = array_values(array_diff($ta, $tb));
        $diffB = array_values(array_diff($tb, $ta));
        sort($inter);
        sort($diffA);
        sort($diffB);
        $t0 = implode(' ', $inter);
        $t1 = trim($t0.' '.implode(' ', $diffA));
        $t2 = trim($t0.' '.implode(' ', $diffB));

        $best = self::ratio($t1, $t2);
        if ($t0 !== '') {
            $best = max($best, self::ratio($t0, $t1), self::ratio($t0, $t2));
        }

        return min(1.0, $best);
    }

    /** Ratcliff/Obershelp ratio (close to difflib.SequenceMatcher.ratio), 0..1. */
    private static function ratio(string $a, string $b): float
    {
        if ($a === '' && $b === '') {
            return 1.0;
        }
        similar_text($a, $b, $percent);

        return $percent / 100;
    }
}
