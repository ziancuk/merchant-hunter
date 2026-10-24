<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Enums\LifecycleStatus;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Services\Geo\BoundingBox;
use App\Services\Geo\Haversine;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * "New open" and "closed" rules (D-35, D-36, D-63, TRD §6).
 *
 * - First sync of an outlet is its baseline: nothing found is "new open".
 * - Later first sightings are new open until 30 days after first_seen_at.
 * - Lifecycle tags close a prospect immediately (reason permanent).
 * - missed_runs++ only for prospects of the answering provider within the outlet's radius when that provider
 *   answered fresh (never stale cache / fallback), once per run; 3 misses close it (missing_runs).
 * - Seen again => reopened, missed_runs reset.
 */
class LifecycleDetector
{
    public const NEW_OPEN_DAYS = 30;

    public const MAX_MISSED_RUNS = 3;

    /** Margin so haversine vs Overpass geodesic differences at the radius edge never cause false misses. */
    public const RADIUS_MARGIN_M = 25;

    public function isBaseline(Outlet $outlet): bool
    {
        return $outlet->baseline_done_at === null;
    }

    public function isNewOpenStillValid(CarbonInterface $firstSeenAt, CarbonInterface $now): bool
    {
        return $firstSeenAt->copy()->addDays(self::NEW_OPEN_DAYS)->gt($now);
    }

    /** Turn off is_new_open past 30 days for every prospect. Returns affected rows. */
    public function expireNewOpen(CarbonInterface $now): int
    {
        return Prospect::query()
            ->where('is_new_open', true)
            ->where('first_seen_at', '<=', $now->copy()->subDays(self::NEW_OPEN_DAYS))
            ->update(['is_new_open' => false]);
    }

    /**
     * Count a miss for prospects of $provider around the outlet that were not in the fresh answer.
     * Returns the number of prospects closed by this call.
     *
     * @param  array<string, true>  $seenPlaceIds  place ids present in the answer (set for O(1) lookup)
     */
    public function processMissing(Outlet $outlet, string $provider, array $seenPlaceIds, CarbonInterface $now, CarbonInterface $runStartedAt, ?int $runId): int
    {
        $radius = (float) config('hunter.radius_m', 2000) - self::RADIUS_MARGIN_M;
        $box = BoundingBox::around((float) $outlet->lat, (float) $outlet->lng, $radius);

        $candidates = Prospect::query()
            ->where('provider', $provider)
            ->where('lifecycle_status', LifecycleStatus::Open->value)
            ->whereBetween('lat', [$box->south, $box->north])
            ->whereBetween('lng', [$box->west, $box->east])
            ->where('last_seen_at', '<', $runStartedAt)
            ->get(['id', 'place_id', 'lat', 'lng', 'missed_runs']);

        $already = $runId !== null ? (array) Cache::get($this->runKey($runId), []) : [];
        $alreadySet = array_flip($already);
        $bump = [];
        $close = [];
        foreach ($candidates as $p) {
            if (isset($seenPlaceIds[$p->place_id]) || isset($alreadySet[$p->id])) {
                continue;
            }
            if (Haversine::distance((float) $outlet->lat, (float) $outlet->lng, (float) $p->lat, (float) $p->lng) > $radius) {
                continue;
            }
            if ($p->missed_runs + 1 >= self::MAX_MISSED_RUNS) {
                $close[] = $p->id;
            } else {
                $bump[] = $p->id;
            }
        }

        foreach (array_chunk($bump, 500) as $ids) {
            Prospect::query()->whereIn('id', $ids)->increment('missed_runs');
        }
        foreach (array_chunk($close, 500) as $ids) {
            Prospect::query()->whereIn('id', $ids)->update([
                'missed_runs' => self::MAX_MISSED_RUNS,
                'lifecycle_status' => LifecycleStatus::Closed->value,
                'closed_at' => $now,
                'closed_reason' => 'missing_runs',
                'score' => 0,
                'score_breakdown' => null,
            ]);
        }
        if ($runId !== null && ($bump !== [] || $close !== [])) {
            Cache::put($this->runKey($runId), array_merge($already, $bump, $close), now()->addDay());
        }

        return count($close);
    }

    private function runKey(int $runId): string
    {
        return "hunter.run.{$runId}.missed";
    }
}
