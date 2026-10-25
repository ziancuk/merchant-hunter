<?php

declare(strict_types=1);

namespace App\Support;

/**
 * System-written activity bodies are persisted as text in the language of the request that created them. When read,
 * a body that matches a known system sentence (in any supported language) is re-rendered in the current language.
 * Free-form notes never match and are returned untouched.
 */
final class ActivityText
{
    private const KEYS = ['contacted', 'opt_out', 'opt_in', 'rejected', 'auto_closed'];

    public static function localize(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return $body;
        }

        foreach (self::KEYS as $key) {
            foreach (Locale::SUPPORTED as $locale) {
                $template = (string) __('api.activity.'.$key, [], $locale);
                $params = self::match($template, $body);
                if ($params !== null) {
                    return __('api.activity.'.$key, $params);
                }
            }
        }

        return $body;
    }

    /** @return array<string, string>|null placeholder values when $body is exactly the template, else null */
    private static function match(string $template, string $body): ?array
    {
        $names = [];
        $pattern = preg_replace_callback('/:([a-z_]+)|[^:]+|:/', static function (array $m) use (&$names): string {
            if (isset($m[1])) {
                $names[] = $m[1];

                return '(.+?)';
            }

            return preg_quote($m[0], '/');
        }, $template);

        if (preg_match('/^'.$pattern.'$/su', $body, $found) !== 1) {
            return null;
        }

        return array_combine($names, array_slice($found, 1)) ?: [];
    }
}
