<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Portable LIKE helper. `ESCAPE '\'` is parsed differently by MySQL (backslash is a string escape there),
 * so a non-special escape character ('!') is used instead; the same SQL works on SQLite, MySQL and PostgreSQL.
 */
final class LikeEscape
{
    public const ESCAPE_CHAR = '!';

    /** Neutralises the LIKE wildcards (% and _) and the escape character itself. */
    public static function escape(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /** Pattern matching `$value` as a literal substring: %value%. */
    public static function contains(string $value): string
    {
        return '%'.self::escape($value).'%';
    }

    /** Raw predicate for a code-controlled column name (never user input); bind the pattern as the only parameter. */
    public static function clause(string $column): string
    {
        return $column.' LIKE ? ESCAPE \''.self::ESCAPE_CHAR.'\'';
    }
}
