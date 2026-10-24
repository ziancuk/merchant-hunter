<?php

declare(strict_types=1);

namespace App\Services\Sync;

use Illuminate\Support\Facades\Cache;

/**
 * White-space results are cached under keys that embed this version (e.g. "whitespace:v{version}:...").
 * Bumping the version after a sync invalidates them without cache tags (TRD §8).
 */
final class WhitespaceCache
{
    public const VERSION_KEY = 'hunter.whitespace.version';

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public static function bump(): int
    {
        Cache::add(self::VERSION_KEY, 1);

        return (int) Cache::increment(self::VERSION_KEY);
    }
}
