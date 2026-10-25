<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\LifecycleStatus;
use App\Enums\ScoreBand;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Models\Province;
use App\Models\SchedulerRun;
use App\Models\User;
use App\Services\Geo\BoundingBox;
use Illuminate\Support\Carbon;

final class DashboardQuery
{
    public function __construct(
        private readonly DigestQuery $digests,
        private readonly WhiteSpaceQuery $whiteSpace,
    ) {}

    /**
     * One aggregate statement over prospects (plus the active-outlet count as a scalar subquery) feeds the HUD,
     * the KPIs and the funnel. Counts are cumulative (D-64); `new_7d` excludes baseline rows.
     */
    private function stats(): object
    {
        return Prospect::query()->toBase()->selectRaw(
            'count(*) as found, '
            .'sum(case when lifecycle_status = ? then 1 else 0 end) as active, '
            .'sum(case when is_baseline = ? and first_seen_at >= ? then 1 else 0 end) as new_7d, '
            .'sum(case when lifecycle_status = ? and score >= ? then 1 else 0 end) as hot, '
            .'count(contacted_at) as contacted, count(demo_at) as demo, count(won_at) as won, count(lost_at) as lost, '
            .'sum(case when won_at >= ? then 1 else 0 end) as deals_month, '
            .'(select count(*) from outlets where is_active = ?) as outlets',
            [
                LifecycleStatus::Open->value, false, now()->subDays(7),
                LifecycleStatus::Open->value, ScoreBand::HOT_MIN,
                now()->startOfMonth(), true,
            ],
        )->first();
    }

    /** @return array<string, int> */
    public function hud(): array
    {
        return $this->hudFrom($this->stats());
    }

    /** @return array<string, mixed> */
    public function kpis(): array
    {
        return $this->kpisFrom($this->stats());
    }

    /** @return array<string, int> cumulative funnel (D-64) */
    public function funnel(): array
    {
        return $this->funnelFrom($this->stats());
    }

    /**
     * KPIs and funnel from a single query.
     *
     * @return array{kpis: array<string, mixed>, funnel: array<string, int>}
     */
    public function kpisAndFunnel(): array
    {
        $row = $this->stats();

        return ['kpis' => $this->kpisFrom($row), 'funnel' => $this->funnelFrom($row)];
    }

    /** @return array<string, int> */
    private function hudFrom(object $row): array
    {
        return [
            'outlets' => (int) $row->outlets,
            'prospects_active' => (int) $row->active,
            'new_7d' => (int) $row->new_7d,
            'hot' => (int) $row->hot,
        ];
    }

    /** @return array<string, mixed> */
    private function kpisFrom(object $row): array
    {
        $contacted = (int) $row->contacted;

        return [
            'outlets' => (int) $row->outlets,
            'prospects_active' => (int) $row->active,
            'new_7d' => (int) $row->new_7d,
            'conversion_rate' => $contacted > 0 ? round((int) $row->won / $contacted, 4) : 0.0,
            'deals_this_month' => (int) $row->deals_month,
        ];
    }

    /** @return array<string, int> */
    private function funnelFrom(object $row): array
    {
        return [
            'found' => (int) $row->found,
            'contacted' => (int) $row->contacted,
            'demo' => (int) $row->demo,
            'won' => (int) $row->won,
            'lost' => (int) $row->lost,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function penetration(): array
    {
        $outlets = Outlet::query()->active()->toBase()->selectRaw('province_id, count(*) as n')->groupBy('province_id')->pluck('n', 'province_id');
        $prospects = Prospect::query()->toBase()
            ->where('lifecycle_status', LifecycleStatus::Open->value)
            ->selectRaw('province_id, count(*) as n, count(won_at) as won')
            ->whereNotNull('province_id')->groupBy('province_id')->get()->keyBy('province_id');

        return Province::query()->orderBy('name')->get(['id', 'name'])->map(function (Province $p) use ($outlets, $prospects) {
            $row = $prospects->get($p->id);
            $count = (int) ($row->n ?? 0);
            $won = (int) ($row->won ?? 0);

            return [
                'province_id' => $p->id,
                'province' => $p->name,
                'outlets' => (int) ($outlets[$p->id] ?? 0),
                'prospects' => $count,
                'won' => $won,
                'penetration' => $count > 0 ? round($won / $count, 4) : 0.0,
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    public function topCategories(int $limit = 8): array
    {
        return Prospect::query()->toBase()
            ->join('categories', 'categories.id', '=', 'prospects.category_id')
            ->where('prospects.lifecycle_status', LifecycleStatus::Open->value)
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.name as category, count(*) as count, avg(prospects.score) as avg_score')
            ->orderByDesc('count')->limit($limit)->get()
            ->map(fn ($r) => ['category' => $r->category, 'count' => (int) $r->count, 'avg_score' => (int) round((float) $r->avg_score)])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function whitespaceTop(int $limit = 5): array
    {
        $cells = $this->whiteSpace->cells(new BoundingBox(-180, -90, 180, 90));
        $provinces = Province::query()->pluck('name', 'id');

        return array_map(fn (array $c) => [
            'cell_id' => $c['id'],
            'lat' => $c['lat'],
            'lng' => $c['lng'],
            'prospects' => $c['prospects'],
            'nearest_outlet_m' => $c['nearest_outlet_m'],
            'province' => $provinces[$c['province_id']] ?? null,
        ], array_slice($cells, 0, $limit));
    }

    /** @return list<array<string, mixed>> */
    public function activityByUser(): array
    {
        $stats = Prospect::query()->toBase()->whereNotNull('handled_by')
            ->selectRaw('handled_by, count(contacted_at) as contacted, count(demo_at) as demo, count(won_at) as won')
            ->groupBy('handled_by')->get()->keyBy('handled_by');

        return User::query()->orderBy('name')->get(['id', 'name'])->map(fn (User $u) => [
            'user_id' => $u->id,
            'name' => $u->name,
            'contacted' => (int) ($stats[$u->id]->contacted ?? 0),
            'demo' => (int) ($stats[$u->id]->demo ?? 0),
            'won' => (int) ($stats[$u->id]->won ?? 0),
        ])->all();
    }

    /** @return array<string, mixed> */
    public function schedulerHealth(): array
    {
        $last = SchedulerRun::query()->where('command', 'hunter:sync')->orderByDesc('started_at')->first();
        $at = $last?->finished_at ?? $last?->started_at;

        return [
            'last_run_at' => $at?->copy()->utc()->toIso8601ZuluString(),
            'status' => $last?->status,
            'age_hours' => $at ? round(max(0, now()->getTimestamp() - $at->getTimestamp()) / 3600, 1) : null,
            'failed_last_7d' => SchedulerRun::query()->where('status', 'failed')->where('started_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function digest(?string $date = null): array
    {
        $day = $date !== null ? Carbon::parse($date) : now();
        $stored = $this->digests->stored($day);
        if ($stored !== null) {
            return $stored;
        }

        return $day->isSameDay(now())
            ? $this->digests->build($day)
            : ['date' => $day->format('Y-m-d'), 'new_open' => [], 'hot' => [], 'follow_up' => []];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $summary = $this->kpisAndFunnel();

        return [
            'kpis' => $summary['kpis'],
            'funnel' => $summary['funnel'],
            'penetration' => $this->penetration(),
            'top_categories' => $this->topCategories(),
            'whitespace_top' => $this->whitespaceTop(),
            'activity_by_user' => $this->activityByUser(),
            'scheduler_health' => $this->schedulerHealth(),
            'digest' => $this->digest(),
        ];
    }
}
