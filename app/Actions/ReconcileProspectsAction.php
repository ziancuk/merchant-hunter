<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\LifecycleStatus;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Services\Dedupe\Deduplicator;
use App\Services\Dedupe\NameNormalizer;
use App\Services\Dedupe\Similarity;
use App\Services\Geo\BoundingBox;
use App\Services\Geo\Haversine;
use App\Services\Sync\WhitespaceCache;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * D-71: when outlets are imported, existing open prospects that are in fact those Hitachi merchants
 * (within 100 m and similar name, TRD §5 rules) are closed with closed_reason=hitachi_merchant and a
 * system activity is written.
 */
class ReconcileProspectsAction
{
    public const REASON = 'hitachi_merchant';

    /**
     * @param  Outlet|iterable<Outlet>  $outlets
     * @return int number of prospects closed
     */
    public function execute(Outlet|iterable $outlets): int
    {
        $outlets = $outlets instanceof Outlet ? [$outlets] : $outlets;
        $now = Carbon::now();
        $closed = 0;

        foreach ($outlets as $outlet) {
            if ($outlet->lat === null || $outlet->lng === null) {
                continue;
            }
            $closed += DB::transaction(fn () => $this->reconcileOutlet($outlet, $now));
        }
        if ($closed > 0) {
            WhitespaceCache::bump();
        }

        return $closed;
    }

    public function __invoke(Outlet|iterable $outlets): int
    {
        return $this->execute($outlets);
    }

    private function reconcileOutlet(Outlet $outlet, CarbonInterface $now): int
    {
        $box = BoundingBox::around((float) $outlet->lat, (float) $outlet->lng, Deduplicator::OUTLET_RADIUS_M);
        $outletName = NameNormalizer::normalize((string) $outlet->name);

        $candidates = Prospect::query()
            ->where('lifecycle_status', LifecycleStatus::Open->value)
            ->whereNotNull('lat')->whereNotNull('lng')
            ->whereBetween('lat', [$box->south, $box->north])
            ->whereBetween('lng', [$box->west, $box->east])
            ->get(['id', 'name', 'lat', 'lng']);

        $closed = 0;
        foreach ($candidates as $p) {
            $distance = Haversine::distance((float) $outlet->lat, (float) $outlet->lng, (float) $p->lat, (float) $p->lng);
            $similarity = Similarity::normalized(NameNormalizer::normalize((string) $p->name), $outletName);
            if (! Deduplicator::isSameMerchant($distance, $similarity)) {
                continue;
            }

            Prospect::query()->whereKey($p->id)->update([
                'lifecycle_status' => LifecycleStatus::Closed->value,
                'closed_at' => $now,
                'closed_reason' => self::REASON,
                'is_new_open' => false,
                'score' => 0,
                'score_breakdown' => null,
            ]);
            ProspectActivity::query()->create([
                'prospect_id' => $p->id,
                'user_id' => null,
                'type' => ActivityType::System,
                'body' => __('api.activity.auto_closed', ['name' => $outlet->name, 'code' => $outlet->code]),
                'meta' => [
                    'reason' => self::REASON,
                    'outlet_id' => $outlet->id,
                    'distance_m' => (int) round($distance),
                    'similarity' => round($similarity, 2),
                ],
            ]);
            $closed++;
        }

        return $closed;
    }
}
