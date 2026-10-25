<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/** UI language negotiation: Indonesian (default) or English, chosen by the client (header or cookie), never guessed from the browser. */
final class Locale
{
    public const SUPPORTED = ['id', 'en'];

    public const DEFAULT = 'id';

    public const COOKIE = 'hunter_locale';

    public const HEADER = 'X-Locale';

    public static function resolve(Request $request): string
    {
        foreach ([$request->header(self::HEADER), $request->cookie(self::COOKIE) ?? ($_COOKIE[self::COOKIE] ?? null)] as $candidate) {
            if (is_string($candidate) && in_array($candidate, self::SUPPORTED, true)) {
                return $candidate;
            }
        }

        return self::DEFAULT;
    }
}
