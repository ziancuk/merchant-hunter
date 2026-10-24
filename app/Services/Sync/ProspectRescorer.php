<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Enums\LifecycleStatus;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Services\Dedupe\OutletIndex;
use App\Services\Scoring\ProspectScorer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Recomputes scores (and, for a full pass, distance/neighbour/density metrics) outside of a sync. */
class ProspectRescorer
{
    public function __construct(
        private readonly ProspectScorer $scorer,
        private readonly LifecycleDetector $lifecycle,
    ) {}

    /**
     * Refresh scores. Without $all only prospects that were never scored with a breakdown are touched;
     * with $all every prospect is rescored and the location metrics are rebuilt first.
     *
     * @return array{scored: int, changed: int, expired_new_open: int, metrics_changed: int}
     */
    public function run(bool $all): array
    {
        $now = Carbon::now();
        $expired = $this->lifecycle->expireNewOpen($now);
        $metricsChanged = $all ? $this->recomputeMetrics() : 0;

        $weights = Category::query()->pluck('weight', 'id')->all();
        $scored = 0;
        $changed = 0;
        $query = Prospect::query()->orderBy('id');
        if (! $all) {
            $query->where(fn ($q) => $q->whereNull('score_breakdown')->orWhere('score', 0));
        }
        $query->chunkById(500, function ($rows) use ($weights, $now, &$scored, &$changed) {
            foreach ($rows as $p) {
                $scored++;
                if ($p->lifecycle_status === LifecycleStatus::Closed) {
                    $score = 0;
                    $breakdown = null;
                } else {
                    $result = $this->scorer->score(ScoreInputFactory::fromAttributes($p->getAttributes(), (int) ($weights[$p->category_id] ?? 0), $now));
                    $score = $result->score;
                    $breakdown = $result->breakdown === [] ? null : $result->breakdown;
                }
                $p->score = $score;
                $p->score_breakdown = $breakdown;
                if ($p->isDirty(['score', 'score_breakdown'])) {
                    $p->save();
                    $changed++;
                }
            }
        });

        return ['scored' => $scored, 'changed' => $changed, 'expired_new_open' => $expired, 'metrics_changed' => $metricsChanged];
    }

    /**
     * Rebuild nearest outlet, neighbour count, density, province and city for every open located prospect
     * using in-memory grids (no per-row spatial queries). Returns the number of rows that changed.
     */
    public function recomputeMetrics(): int
    {
        $metrics = new ProspectMetrics(new OutletIndex(Outlet::query()->located()->get()));
        $grid = new PointGrid;
        $base = DB::table('prospects')->where('lifecycle_status', LifecycleStatus::Open->value)->whereNotNull('lat')->whereNotNull('lng');
        (clone $base)->select('id', 'lat', 'lng')->orderBy('id')->chunkById(2000, function ($rows) use ($grid) {
            foreach ($rows as $r) {
                $grid->add('id:'.$r->id, (float) $r->lat, (float) $r->lng);
            }
        });

        $changed = 0;
        $stamp = Carbon::now()->format('Y-m-d H:i:s');
        (clone $base)->orderBy('id')->chunkById(1000, function ($rows) use ($metrics, $grid, &$changed, $stamp) {
            foreach ($rows as $r) {
                $m = $metrics->outletMetrics((float) $r->lat, (float) $r->lng);
                $new = [
                    'nearest_outlet_id' => $m['nearest_outlet_id'],
                    'nearest_outlet_distance_m' => $m['nearest_outlet_distance_m'],
                    'neighbor_count_500m' => $m['neighbor_count_500m'],
                    'area_density' => $metrics->density($grid, (float) $r->lat, (float) $r->lng, 'id:'.$r->id),
                    'province_id' => $m['province_id'],
                    'city' => $r->address_source === 'reverse_geocode' && filled($r->city) ? $r->city : ($m['city'] !== null ? mb_substr($m['city'], 0, 80) : null),
                ];
                $dirty = false;
                foreach ($new as $k => $v) {
                    if ($r->{$k} != $v) {
                        $dirty = true;
                        break;
                    }
                }
                if ($dirty) {
                    DB::table('prospects')->where('id', $r->id)->update($new + ['updated_at' => $stamp]);
                    $changed++;
                }
            }
        });

        return $changed;
    }
}
