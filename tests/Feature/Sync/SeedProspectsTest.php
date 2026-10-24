<?php

use App\Models\Category;
use App\Models\GeocodeQueueItem;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\SchedulerRun;
use App\Models\SchedulerRunOutlet;
use App\Services\Sync\WhitespaceCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Sync\SyncFixtures as F;

beforeEach(function () {
    Carbon::setTestNow('2026-10-24 09:00:00');
    Http::preventStrayRequests();
    $this->w = F::world();
    Category::factory()->create(['key' => 'restaurant', 'weight' => 85]);
    Category::factory()->create(['key' => 'minimarket', 'weight' => 90]);
    // five outlets in two neighbourhoods so densities overlap
    foreach ([[-6.20, 106.80], [-6.205, 106.805], [-6.21, 106.81], [-6.50, 106.80], [-6.505, 106.795]] as $i => [$lat, $lng]) {
        F::outlet($this->w['province'], 'OUT-'.($i + 1), $lat, $lng);
    }
});

afterEach(fn () => Carbon::setTestNow());

it('seeds simulated prospects for every active located outlet without any network call', function () {
    $this->artisan('hunter:seed-prospects', ['--offline' => true])->assertSuccessful();

    $count = Prospect::count();
    expect($count)->toBeGreaterThanOrEqual(5 * 55)->toBeLessThanOrEqual(5 * 95 + 40);
    expect(Prospect::where('provider', '!=', 'mock')->count())->toBe(0)
        ->and(Prospect::where('is_simulated', false)->count())->toBe(0)
        ->and(Prospect::whereNull('nearest_outlet_id')->count())->toBe(0)
        ->and(Prospect::whereNull('province_id')->count())->toBe(0)
        ->and(Prospect::where('area_density', '>', 0)->count())->toBeGreaterThan(50)
        ->and(Prospect::where('score', '>', 0)->count())->toBeGreaterThan(100);
    expect(Prospect::whereNotNull('phone')->where('phone', 'not like', '0999-%')->count())->toBe(0);
    expect(Outlet::whereNull('baseline_done_at')->count())->toBe(0);
    expect(GeocodeQueueItem::count())->toBe(Prospect::whereNull('address')->count());
    expect(WhitespaceCache::version())->toBeGreaterThan(1);

    $run = SchedulerRun::sole();
    expect($run->command)->toBe('hunter:sync')->and($run->status)->toBe('success')->and($run->outlets_total)->toBe(5)
        ->and($run->outlets_ok)->toBe(5)->and($run->details['mode'])->toBe('offline_seed')->and($run->details['seed'])->toBe(42)
        ->and($run->prospects_new)->toBe($count)->and($run->finished_at)->not->toBeNull();
    expect(SchedulerRunOutlet::where('scheduler_run_id', $run->id)->count())->toBe(5);
});

it('simulates a second run: baseline vs new open prospects and a few closures', function () {
    $this->artisan('hunter:seed-prospects', ['--offline' => true])->assertSuccessful();

    $total = Prospect::count();
    $extras = Prospect::where('is_baseline', false)->count();
    expect($extras)->toBeGreaterThan(0)->and($extras)->toBeLessThanOrEqual((int) ceil($total * 0.05));
    expect(Prospect::where('is_baseline', false)->where('is_new_open', true)->count())->toBeGreaterThan(0);
    expect(Prospect::where('is_baseline', true)->where('is_new_open', true)->count())->toBe(0);
    $week = Prospect::where('is_baseline', false)->where('first_seen_at', '>=', now()->subDays(7))->count();
    expect($week)->toBe($extras);

    $closed = Prospect::where('lifecycle_status', 'closed')->get();
    expect($closed->count())->toBeGreaterThanOrEqual(3)
        ->and($closed->pluck('score')->unique()->all())->toBe([0])
        ->and($closed->pluck('closed_reason')->unique()->diff(['permanent', 'missing_runs'])->count())->toBe(0);
});

it('is deterministic for a seed and different for another seed', function () {
    $snapshot = fn () => Prospect::orderBy('place_id')->get(['place_id', 'name', 'lat', 'lng', 'score', 'area_density', 'lifecycle_status', 'is_new_open'])->toArray();

    $this->artisan('hunter:seed-prospects', ['--offline' => true, '--seed' => 42])->assertSuccessful();
    $a = $snapshot();
    $this->artisan('hunter:demo-reset')->assertSuccessful();
    $b = $snapshot();
    expect($b)->toBe($a);

    $this->artisan('hunter:demo-reset')->assertSuccessful();
    Prospect::query()->delete(); // bypass queue FK order for the comparison run only
    $this->artisan('hunter:seed-prospects', ['--offline' => true, '--seed' => 7])->assertSuccessful();
    expect($snapshot())->not->toBe($a);
});

it('does not duplicate prospects when seeded twice', function () {
    $this->artisan('hunter:seed-prospects', ['--offline' => true])->assertSuccessful();
    $count = Prospect::count();
    $this->artisan('hunter:seed-prospects', ['--offline' => true])->assertSuccessful();
    expect(Prospect::count())->toBe($count);
});

it('fails clearly without outlets', function () {
    Outlet::query()->delete();
    $this->artisan('hunter:seed-prospects', ['--offline' => true])->assertFailed();
    expect(Prospect::count())->toBe(0);
});

it('without --offline runs the real sync pipeline', function () {
    Http::fake(['*' => Http::response(['elements' => []])]);
    config(['hunter.overpass.delay_seconds' => 0]);
    $this->artisan('hunter:seed-prospects')->assertSuccessful();
    expect(SchedulerRun::first()->command)->toBe('hunter:sync')->and(SchedulerRun::first()->details)->not->toHaveKey('mode');
});

it('demo-reset clears prospects, activities, queue and runs, then reseeds', function () {
    $this->artisan('hunter:seed-prospects', ['--offline' => true])->assertSuccessful();
    $p = Prospect::first();
    ProspectActivity::create(['prospect_id' => $p->id, 'type' => 'note', 'body' => 'x']);
    $firstRunId = SchedulerRun::first()->id;

    $this->artisan('hunter:demo-reset')->assertSuccessful();

    expect(ProspectActivity::count())->toBe(0)->and(SchedulerRun::count())->toBe(1)->and(SchedulerRun::first()->id)->not->toBe($firstRunId)
        ->and(Prospect::count())->toBeGreaterThan(250);
});

it('demo-reset refuses in production unless forced', function () {
    $this->artisan('hunter:seed-prospects', ['--offline' => true])->assertSuccessful();
    $count = Prospect::count();

    $this->app['env'] = 'production';
    $this->artisan('hunter:demo-reset')->expectsOutputToContain('Refusing')->assertFailed();
    expect(Prospect::count())->toBe($count)->and(SchedulerRun::count())->toBe(1);

    $this->artisan('hunter:demo-reset', ['--force' => true])->assertSuccessful();
    expect(SchedulerRun::count())->toBe(1);
});
