<?php

use App\DTO\BusinessDTO;
use App\DTO\OutletPoint;
use App\DTO\ProviderResult;
use App\Jobs\FinalizeRunJob;
use App\Jobs\SyncOutletJob;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Models\SchedulerRun;
use App\Models\SchedulerRunOutlet;
use App\Models\Setting;
use App\Services\Providers\ProviderException;
use App\Services\Sync\RunFinalizer;
use App\Services\Sync\SyncRunner;
use App\Services\Sync\WhitespaceCache;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Sync\FakeProvider;
use Tests\Feature\Sync\SyncFixtures as F;

beforeEach(function () {
    Carbon::setTestNow('2026-10-01 01:00:00');
    Cache::flush();
    $this->w = F::world();
    // three outlets ~10 km apart so their areas do not overlap
    $this->o1 = F::outlet($this->w['province'], 'OUT-1', -6.20, 106.80, ['last_synced_at' => now()->subDays(3)]);
    $this->o2 = F::outlet($this->w['province'], 'OUT-2', -6.30, 106.80, ['last_synced_at' => now()->subDays(5)]);
    $this->o3 = F::outlet($this->w['province'], 'OUT-3', -6.40, 106.80, ['last_synced_at' => now()->subDays(1)]);
    $this->osm = new FakeProvider('osm');
    // two businesses around whichever outlet is queried
    $this->osm->answer = function (OutletPoint $c) {
        $mk = fn (int $i, float $dn) => new BusinessDTO(
            'osm', "{$c->code}/node/$i", "Kedai {$c->code} $i", 'cafe', $c->lat + $dn / 111_195, $c->lng, fetchedAt: CarbonImmutable::now(),
        );

        return new ProviderResult([$mk(1, 300), $mk(2, 600)], false, true, 1);
    };
    F::registry($this->osm);
});

afterEach(fn () => Carbon::setTestNow());

it('syncs all active located outlets and records the run', function () {
    $this->artisan('hunter:sync')->assertSuccessful();

    $run = SchedulerRun::first();
    expect($run->command)->toBe('hunter:sync')->and($run->status)->toBe('success')
        ->and($run->outlets_total)->toBe(3)->and($run->outlets_ok)->toBe(3)->and($run->outlets_failed)->toBe(0)
        ->and($run->prospects_new)->toBe(6)->and($run->prospects_updated)->toBe(0)->and($run->api_calls)->toBe(3)
        ->and($run->finished_at)->not->toBeNull()->and($run->error)->toBeNull();
    expect(Prospect::count())->toBe(6);
    expect(SchedulerRunOutlet::where('scheduler_run_id', $run->id)->where('status', 'ok')->count())->toBe(3);
    $line = SchedulerRunOutlet::where('outlet_id', $this->o1->id)->first();
    expect($line->provider)->toBe('osm')->and($line->fetched_count)->toBe(2)->and($line->new_count)->toBe(2);
    expect(Outlet::whereNull('baseline_done_at')->count())->toBe(0);
    expect(Prospect::where('is_baseline', true)->count())->toBe(6);
});

it('processes the least recently synced outlets first and honours --limit', function () {
    $this->artisan('hunter:sync', ['--limit' => 2])->assertSuccessful();
    expect(array_map(fn (OutletPoint $p) => $p->code, $this->osm->calls))->toBe(['OUT-2', 'OUT-1']);
    expect(SchedulerRun::first()->outlets_total)->toBe(2);
});

it('honours the sync.max_outlets_per_run setting', function () {
    Setting::write('sync.max_outlets_per_run', 1);
    $this->artisan('hunter:sync')->assertSuccessful();
    expect(array_map(fn (OutletPoint $p) => $p->code, $this->osm->calls))->toBe(['OUT-2']);
});

it('syncs one outlet by code or id with --outlet', function () {
    $this->artisan('hunter:sync', ['--outlet' => 'OUT-3'])->assertSuccessful();
    expect(array_map(fn (OutletPoint $p) => $p->code, $this->osm->calls))->toBe(['OUT-3']);

    $this->osm->calls = [];
    $this->artisan('hunter:sync', ['--outlet' => (string) $this->o1->id])->assertSuccessful();
    expect(array_map(fn (OutletPoint $p) => $p->code, $this->osm->calls))->toBe(['OUT-1']);
});

it('skips inactive outlets and outlets without coordinates', function () {
    $this->o1->update(['is_active' => false]);
    F::outlet($this->w['province'], 'OUT-4', 0, 0, ['lat' => null, 'lng' => null]);
    $this->artisan('hunter:sync')->assertSuccessful();
    expect(SchedulerRun::first()->outlets_total)->toBe(2);
});

it('is idempotent: a second run does not duplicate prospects', function () {
    $this->artisan('hunter:sync')->assertSuccessful();
    Carbon::setTestNow('2026-10-02 01:00:00');
    $this->artisan('hunter:sync')->assertSuccessful();

    expect(Prospect::count())->toBe(6)->and(SchedulerRun::count())->toBe(2);
    $second = SchedulerRun::orderByDesc('id')->first();
    expect($second->prospects_new)->toBe(0)->and($second->status)->toBe('success');
    expect(Prospect::where('is_new_open', true)->count())->toBe(0);
});

it('ends partial when some outlets fail and failed when all fail', function () {
    $ok = $this->osm->answer;
    $this->osm->answer = fn (OutletPoint $c, $cats) => $c->code === 'OUT-2' ? new ProviderException('Overpass answered HTTP 504') : $ok($c, $cats);

    $this->artisan('hunter:sync')->assertSuccessful();
    $run = SchedulerRun::first();
    expect($run->status)->toBe('partial')->and($run->outlets_ok)->toBe(2)->and($run->outlets_failed)->toBe(1)
        ->and($run->error)->toContain('504')->and($run->prospects_new)->toBe(4);
    $bad = SchedulerRunOutlet::where('outlet_id', $this->o2->id)->first();
    expect($bad->status)->toBe('failed')->and($bad->error)->toContain('504');
    expect($this->o2->fresh()->baseline_done_at)->toBeNull();

    $this->osm->answer = new ProviderException('everything down');
    $this->artisan('hunter:sync')->assertFailed();
    $last = SchedulerRun::orderByDesc('id')->first();
    expect($last->status)->toBe('failed')->and($last->outlets_failed)->toBe(3)->and($last->outlets_ok)->toBe(0);
});

it('marks cached answers and finishes success', function () {
    $this->osm->answer = new ProviderResult([], cached: true, fresh: false);
    $this->artisan('hunter:sync')->assertSuccessful();
    $run = SchedulerRun::first();
    expect($run->status)->toBe('success')->and($run->details['cached_outlets'])->toBe(3);
    expect(SchedulerRunOutlet::where('status', 'cached')->count())->toBe(3);
});

it('can be forced to one provider with --provider', function () {
    $mock = new FakeProvider('mock');
    $mock->answer = [];
    F::registry($this->osm, $mock);

    $this->artisan('hunter:sync', ['--provider' => 'mock', '--outlet' => 'OUT-1'])->assertSuccessful();
    expect($mock->calls)->toHaveCount(1)->and($this->osm->calls)->toHaveCount(0);
    expect(SchedulerRunOutlet::first()->provider)->toBe('mock');
});

it('finishes immediately with nothing to do', function () {
    Outlet::query()->update(['is_active' => false]);
    $this->artisan('hunter:sync')->assertSuccessful();
    expect(SchedulerRun::first()->status)->toBe('success')->and(SchedulerRun::first()->outlets_total)->toBe(0);
});

it('invalidates the white space cache version after a run', function () {
    $before = WhitespaceCache::version();
    $this->artisan('hunter:sync')->assertSuccessful();
    expect(WhitespaceCache::version())->toBeGreaterThan($before)->and(WhitespaceCache::VERSION_KEY)->toBe('hunter.whitespace.version');
});

it('configures the sync job as specified in TRD §6', function () {
    $job = new SyncOutletJob(5, 9, 'osm', true);
    expect($job->tries)->toBe(0)->and($job->maxExceptions)->toBe(3)->and($job->backoff)->toBe([30, 60, 120])
        ->and($job->queue)->toBe('sync')
        ->and($job->uniqueId())->toBe('sync-outlet-5-run-9')
        ->and($job->retryUntil()->getTimestamp())->toBe(now()->addMinutes(30)->getTimestamp());
    $mw = $job->middleware();
    expect($mw[0])->toBeInstanceOf(WithoutOverlapping::class)->and($mw[0]->key)->toBe('sync-outlet-5');
    expect($job)->toBeInstanceOf(ShouldBeUnique::class);
});

it('records the outlet as failed when the job gives up', function () {
    $run = SchedulerRun::create(['command' => 'hunter:sync', 'status' => 'running', 'started_at' => now(), 'outlets_total' => 1]);
    (new SyncOutletJob($this->o1->id, $run->id))->failed(new RuntimeException('boom'));
    expect(SchedulerRunOutlet::first()->status)->toBe('failed')->and(SchedulerRunOutlet::first()->error)->toBe('boom');

    app(RunFinalizer::class)->finalize($run->id);
    expect($run->fresh()->status)->toBe('failed');
});

it('marks outlets that never reported as failed when finalizing and finalizes only once', function () {
    $run = SchedulerRun::create(['command' => 'hunter:sync', 'status' => 'running', 'started_at' => now()->subSeconds(2), 'outlets_total' => 3]);
    SchedulerRunOutlet::create(['scheduler_run_id' => $run->id, 'outlet_id' => $this->o1->id, 'provider' => 'osm', 'status' => 'ok', 'new_count' => 4]);

    dispatch(new FinalizeRunJob($run->id));
    $run->refresh();
    expect($run->status)->toBe('partial')->and($run->outlets_failed)->toBe(2)->and($run->prospects_new)->toBe(4)
        ->and($run->duration_ms)->toBe(2000);

    $run->update(['status' => 'success']);
    app(RunFinalizer::class)->finalize($run->id);
    expect($run->fresh()->status)->toBe('success');
    expect(app(RunFinalizer::class)->finalize(9999))->toBeNull();
});

it('skips outlets that disappeared or lost their coordinates before the job ran', function () {
    $run = SchedulerRun::create(['command' => 'hunter:sync', 'status' => 'running', 'started_at' => now(), 'outlets_total' => 2]);
    $this->o1->forceFill(['lat' => null, 'lng' => null])->save();
    app()->call([new SyncOutletJob($this->o1->id, $run->id), 'handle']);
    app()->call([new SyncOutletJob(99999, $run->id), 'handle']);

    expect(SchedulerRunOutlet::first()->status)->toBe('skipped')->and(SchedulerRunOutlet::count())->toBe(1);
});

it('selects outlets through SyncRunner without dispatching', function () {
    $selected = app(SyncRunner::class)->selectOutlets(null, 2);
    expect($selected->pluck('code')->all())->toBe(['OUT-2', 'OUT-1']);
});
