<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use App\Services\Providers\Sleeper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * One shared limit for reverse and forward geocoding (TRD §6, D-41): at most requests_per_minute
 * (default 4 => one request every 15 s) across all processes, serialised with a cache lock.
 */
class NominatimRateLimiter
{
    public const KEY_LAST = 'hunter.nominatim.last_request_at';

    public const KEY_LOCK = 'hunter.nominatim.lock';

    public const KEY_BLOCKED_UNTIL = 'hunter.nominatim.blocked_until';

    /** After a 403/429 geocoding stays off this long. */
    public const BLOCK_MINUTES = 60;

    public function __construct(private readonly Sleeper $sleeper) {}

    public function interval(): float
    {
        return 60 / max(1, (int) config('hunter.geocoding.requests_per_minute', 4));
    }

    /** Wait (via the sleeper) until the next request is allowed, then reserve the slot. */
    public function acquire(): void
    {
        Cache::lock(self::KEY_LOCK, 120)->block(120, function () {
            $now = $this->now();
            $last = Cache::get(self::KEY_LAST);
            $wait = $last !== null ? $this->interval() - ($now - (float) $last) : 0.0;
            if ($wait > 0) {
                $this->sleeper->sleep($wait);
                $now += $wait;
            }
            Cache::forever(self::KEY_LAST, $now);
        });
    }

    public function blocked(): bool
    {
        $until = Cache::get(self::KEY_BLOCKED_UNTIL);

        return $until !== null && Carbon::now()->timestamp < (int) $until;
    }

    public function block(): void
    {
        Cache::put(self::KEY_BLOCKED_UNTIL, Carbon::now()->addMinutes(self::BLOCK_MINUTES)->timestamp, now()->addMinutes(self::BLOCK_MINUTES + 1));
    }

    private function now(): float
    {
        return Carbon::now()->getPreciseTimestamp(3) / 1000;
    }
}
