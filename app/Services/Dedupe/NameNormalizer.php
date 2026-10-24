<?php

declare(strict_types=1);

namespace App\Services\Dedupe;

/** Business-name normalisation for fuzzy matching (TRD §5 point 3). */
final class NameNormalizer
{
    /** Generic words that carry no identity. */
    public const STOPWORDS = ['toko', 'warung', 'pt', 'cv', 'ud', 'resto', 'restoran', 'cafe', 'kafe', 'kedai'];

    public static function normalize(string $name): string
    {
        $clean = self::clean($name);
        $tokens = array_values(array_filter(
            explode(' ', $clean),
            fn (string $t) => $t !== '' && ! in_array($t, self::STOPWORDS, true),
        ));

        // A name made only of generic words ("Toko") keeps its cleaned form so it can still be compared.
        return $tokens === [] ? $clean : implode(' ', $tokens);
    }

    /** Lower-case, strip diacritics and punctuation, collapse whitespace. */
    private static function clean(string $name): string
    {
        $s = mb_strtolower(trim($name), 'UTF-8');
        $s = self::stripDiacritics($s);
        $s = preg_replace('/[^a-z0-9\s]+/u', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    private static function stripDiacritics(string $s): string
    {
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($s, \Normalizer::FORM_D);
            if ($decomposed !== false) {
                return preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $s;
            }
        }

        return $s;
    }
}
