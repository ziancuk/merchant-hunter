<?php

use App\Enums\PipelineStatus;
use App\Enums\ScoreBand;
use App\Models\DailyDigest;
use App\Repositories\DashboardQuery;
use App\Repositories\PageProps;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(8, 0));
    dm_login();
});

function da_fixture(): void
{
    dm_outlet(['is_active' => true]);
    dm_outlet(['is_active' => false]);
    dm_prospect(['score' => 90, 'is_baseline' => false, 'first_seen_at' => now()->subDays(2)]);                       // hot + new_7d
    dm_prospect(['score' => ScoreBand::HOT_MIN, 'is_baseline' => true]);                                              // hot, baseline
    dm_prospect(['score' => ScoreBand::HOT_MIN - 1, 'is_baseline' => false, 'first_seen_at' => now()->subDays(20)]); // warm, old
    dm_prospect(['score' => 99, 'lifecycle_status' => 'closed', 'contacted_at' => now()->subDays(40), 'lost_at' => now()->subDays(10)]);
    dm_prospect(['score' => 50, 'contacted_at' => now()->subDays(5), 'demo_at' => now()->subDays(4), 'won_at' => now()->subDay(), 'pipeline_status' => 'won']);
}

function da_count_queries(callable $fn): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $fn();
    $n = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $n;
}

it('computes hud, kpis and funnel from one aggregate statement each way', function () {
    da_fixture();
    $q = app(DashboardQuery::class);

    expect($q->hud())->toBe(['outlets' => 1, 'prospects_active' => 4, 'new_7d' => 3, 'hot' => 2]);
    expect($q->kpis())->toBe(['outlets' => 1, 'prospects_active' => 4, 'new_7d' => 3, 'conversion_rate' => 0.5, 'deals_this_month' => 1]);
    expect($q->funnel())->toBe(['found' => 5, 'contacted' => 2, 'demo' => 1, 'won' => 1, 'lost' => 1]);

    expect(da_count_queries(fn () => $q->hud()))->toBe(1)
        ->and(da_count_queries(fn () => $q->kpisAndFunnel()))->toBe(1);
});

it('uses a single hot threshold constant shared by band, hud and grid', function () {
    expect(ScoreBand::HOT_MIN)->toBe(75)
        ->and(ScoreBand::fromScore(74))->toBe(ScoreBand::Warm)
        ->and(ScoreBand::fromScore(75))->toBe(ScoreBand::Hot);
});

it('lists the pipeline statuses from the enum in filter options', function () {
    expect(app(PageProps::class)->filterOptions()['pipeline_statuses'])->toBe(array_map(fn ($s) => $s->value, PipelineStatus::cases()));
});

it('stores digest_date as a plain date and finds it with an equality lookup (idempotent generation)', function () {
    da_fixture();

    $this->artisan('hunter:digest')->assertSuccessful();
    $this->artisan('hunter:digest')->assertSuccessful();

    expect(DailyDigest::query()->count())->toBe(1)
        ->and(DB::table('daily_digests')->value('digest_date'))->toBe('2026-10-24');
    $this->getJson('/api/digest?date=2026-10-24')->assertOk()->assertJsonPath('date', '2026-10-24');
});
