<?php

use App\DTO\ProviderResult;
use App\DTO\ScoreInput;
use App\DTO\ScoreResult;
use App\Models\DataSource;
use App\Models\GeocodeQueueItem;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Models\SchedulerRun;
use App\Models\Setting;
use App\Services\Providers\ProviderException;
use App\Services\Scoring\ProspectScorer;
use App\Services\Sync\SyncService;
use Illuminate\Support\Carbon;
use Tests\Feature\Sync\FakeProvider;
use Tests\Feature\Sync\SyncFixtures as F;

function night(int $n): void
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 01:00:00')->addDays($n));
}

beforeEach(function () {
    night(0);
    $this->w = F::world();
    $this->outlet = F::outlet($this->w['province']);
    $this->osm = new FakeProvider('osm');
    F::registry($this->osm);
    $this->sync = fn (?Outlet $o = null, array $args = []) => app(SyncService::class)->syncOutlet(($o ?? $this->outlet)->fresh(), ...$args);
});

afterEach(fn () => Carbon::setTestNow());

it('treats the first sync as baseline and computes metrics, score and geocode queue', function () {
    // second outlet 400 m from the first business, inactive-free; a third prospect near the first
    F::outlet($this->w['province'], 'OUT-2', ...F::at(900, 0));
    $this->osm->answer = [
        F::biz('node/1', 300, 0, ['address' => 'Jl. Satu 1', 'phone' => '021111', 'website' => 'https://a.test', 'rating' => 4.5, 'reviews' => 30]),
        F::biz('node/2', 500, 100),
        F::biz('node/3', 1500, 0, ['category' => 'bakery']),
    ];

    $r = ($this->sync)();

    expect($r->status)->toBe('ok')->and($r->fetched)->toBe(3)->and($r->new)->toBe(3)->and($r->apiCalls)->toBe(1);
    expect(Prospect::count())->toBe(3);
    $p1 = Prospect::where('place_id', 'node/1')->first();
    expect($p1->is_baseline)->toBeTrue()->and($p1->is_new_open)->toBeFalse()
        ->and($p1->provider)->toBe('osm')->and($p1->is_simulated)->toBeFalse()
        ->and($p1->address)->toBe('Jl. Satu 1')->and($p1->address_source)->toBe('source')
        ->and($p1->city)->toBe('Jakarta Pusat')->and($p1->province_id)->toBe($this->w['province']->id)
        ->and($p1->nearest_outlet_id)->toBe($this->outlet->id)
        ->and($p1->nearest_outlet_distance_m)->toBeBetween(295, 305)
        ->and($p1->neighbor_count_500m)->toBe(1)
        ->and($p1->area_density)->toBe(1)               // node/2 is ~224 m away; node/3 too far
        ->and($p1->score)->toBe(80)                     // stub scorer = category weight
        ->and($p1->lifecycle_status->value)->toBe('open');
    expect(Prospect::where('place_id', 'node/3')->first()->category_id)->toBe($this->w['bakery']->id);
    // node/3 is nearer the second outlet (900 m north of the first, so 600 m away)
    expect(Prospect::where('place_id', 'node/3')->first()->nearest_outlet_distance_m)->toBeBetween(595, 605);

    $this->outlet->refresh();
    expect($this->outlet->baseline_done_at)->not->toBeNull()->and($this->outlet->last_synced_at)->not->toBeNull();

    // only address-less prospects are queued, priority = score
    expect(GeocodeQueueItem::count())->toBe(2);
    $q = GeocodeQueueItem::where('prospect_id', Prospect::where('place_id', 'node/3')->value('id'))->first();
    expect($q->status)->toBe('pending')->and($q->priority)->toBe(70)->and($q->attempts)->toBe(0);
    expect(GeocodeQueueItem::where('prospect_id', $p1->id)->exists())->toBeFalse();
});

it('flags prospects first seen after the baseline as new open for 30 days', function () {
    $this->osm->answer = [F::biz('node/1')];
    ($this->sync)();

    night(1);
    $this->osm->answer = [F::biz('node/1'), F::biz('node/2', 400)];
    $r = ($this->sync)();
    expect($r->new)->toBe(1);

    $new = Prospect::where('place_id', 'node/2')->first();
    expect($new->is_new_open)->toBeTrue()->and($new->is_baseline)->toBeFalse();
    expect(Prospect::where('place_id', 'node/1')->first()->is_new_open)->toBeFalse();

    night(30);
    ($this->sync)(null, [null, false]);
    expect($new->fresh()->is_new_open)->toBeTrue(); // 29 days after first sight

    night(32);
    $this->osm->answer = [F::biz('node/1'), F::biz('node/2', 400)];
    ($this->sync)();
    expect($new->fresh()->is_new_open)->toBeFalse();
});

it('is idempotent: re-running does not duplicate prospects', function () {
    $this->osm->answer = [F::biz('node/1'), F::biz('node/2', 400)];
    ($this->sync)();
    night(1);
    $r = ($this->sync)();

    expect(Prospect::count())->toBe(2)->and($r->new)->toBe(0)->and($r->updated)->toBe(0);
    expect(GeocodeQueueItem::count())->toBe(2);
});

it('counts a changed prospect as updated and keeps first_seen_at', function () {
    $this->osm->answer = [F::biz('node/1', 300, 0, ['name' => 'Old Name'])];
    ($this->sync)();
    $first = Prospect::first()->first_seen_at;

    night(1);
    $this->osm->answer = [F::biz('node/1', 300, 0, ['name' => 'New Name', 'phone' => '021999'])];
    $r = ($this->sync)();

    $p = Prospect::first();
    expect($r->updated)->toBe(1)->and($p->name)->toBe('New Name')->and($p->phone)->toBe('021999')
        ->and($p->first_seen_at->equalTo($first))->toBeTrue()->and($p->last_seen_at->toDateString())->toBe('2026-10-02');
});

it('increments missed_runs per fresh sync and closes after 3, then reopens when seen again', function () {
    $this->osm->answer = [F::biz('node/1'), F::biz('node/2', 400)];
    ($this->sync)();

    foreach ([1, 2] as $n) {
        night($n);
        $this->osm->answer = [F::biz('node/2', 400)];
        ($this->sync)();
        $p = Prospect::where('place_id', 'node/1')->first();
        expect($p->missed_runs)->toBe($n)->and($p->lifecycle_status->value)->toBe('open');
    }

    night(3);
    $r = ($this->sync)();
    $p = Prospect::where('place_id', 'node/1')->first();
    expect($p->lifecycle_status->value)->toBe('closed')->and($p->closed_reason)->toBe('missing_runs')
        ->and($p->closed_at)->not->toBeNull()->and($p->missed_runs)->toBe(3)->and($p->score)->toBe(0)
        ->and($r->closed)->toBe(1);
    expect(Prospect::where('place_id', 'node/2')->first()->missed_runs)->toBe(0);

    night(4);
    $this->osm->answer = [F::biz('node/1'), F::biz('node/2', 400)];
    ($this->sync)();
    $p->refresh();
    expect($p->lifecycle_status->value)->toBe('open')->and($p->closed_at)->toBeNull()->and($p->closed_reason)->toBeNull()
        ->and($p->missed_runs)->toBe(0)->and($p->score)->toBe(80);
});

it('does not count misses for prospects beyond 2 km of the outlet', function () {
    $this->osm->answer = [F::biz('node/near', 300), F::biz('node/far', 2600)];
    ($this->sync)();

    night(1);
    $this->osm->answer = [];
    ($this->sync)();

    expect(Prospect::where('place_id', 'node/near')->first()->missed_runs)->toBe(1);
    expect(Prospect::where('place_id', 'node/far')->first()->missed_runs)->toBe(0);
});

it('does not count misses on stale cache answers', function () {
    $this->osm->answer = [F::biz('node/1')];
    ($this->sync)();

    night(1);
    $this->osm->answer = new ProviderResult([], cached: true, fresh: false, apiCalls: 0);
    $r = ($this->sync)();

    expect($r->status)->toBe('cached');
    expect(Prospect::first()->missed_runs)->toBe(0);
});

it('does not count misses for prospects of another provider (fallback answered)', function () {
    $this->osm->answer = [F::biz('node/1')];
    ($this->sync)();

    night(1);
    $this->osm->answer = new ProviderException('down');
    $google = new FakeProvider('google');
    $google->answer = [F::biz('g1', 300, 0, ['provider' => 'google'])];
    F::registry($this->osm, $google);
    DataSource::where('key', 'google')->update(['is_active' => true]);

    $r = ($this->sync)();
    expect($r->provider)->toBe('google')->and($r->status)->toBe('ok');
    expect(Prospect::where('place_id', 'node/1')->first()->missed_runs)->toBe(0);
    expect(Prospect::where('provider', 'google')->count())->toBe(1);
});

it('closes immediately on lifecycle tags and never creates already-closed prospects', function () {
    $this->osm->answer = [F::biz('node/1'), F::biz('node/2', 400)];
    ($this->sync)();

    night(1);
    $this->osm->answer = [F::biz('node/1', 300, 0, ['closed' => true]), F::biz('node/2', 400), F::biz('node/9', 600, 0, ['closed' => true])];
    $r = ($this->sync)();

    $p = Prospect::where('place_id', 'node/1')->first();
    expect($p->lifecycle_status->value)->toBe('closed')->and($p->closed_reason)->toBe('permanent')
        ->and($r->closed)->toBe(1)->and($p->score)->toBe(0);
    expect(Prospect::where('place_id', 'node/9')->exists())->toBeFalse();
    expect(Prospect::where('place_id', 'node/2')->first()->lifecycle_status->value)->toBe('open');
});

it('counts a miss only once per run for overlapping outlets and skips prospects seen in the run', function () {
    $o2 = F::outlet($this->w['province'], 'OUT-2', ...F::at(500, 0));
    $this->osm->answer = [F::biz('node/1', 250)];
    ($this->sync)();
    ($this->sync)($o2);

    night(1);
    $run = SchedulerRun::create(['command' => 'hunter:sync', 'status' => 'running', 'started_at' => now(), 'outlets_total' => 2]);
    $this->osm->answer = [];
    app(SyncService::class)->syncOutlet($this->outlet->fresh(), null, null, false, $run);
    app(SyncService::class)->syncOutlet($o2->fresh(), null, null, false, $run);

    expect(Prospect::first()->missed_runs)->toBe(1);
});

it('excludes businesses that are Hitachi merchants', function () {
    [$lat, $lng] = F::at(40);
    $this->osm->answer = [
        F::biz('node/self', 40, 0, ['name' => 'Toko Outlet OUT-1 Merchant']),
        F::biz('node/other', 40, 0, ['name' => 'Bengkel Lain']),
    ];
    $r = ($this->sync)();

    expect($r->excluded)->toBe(1)->and($r->new)->toBe(1);
    expect(Prospect::pluck('place_id')->all())->toBe(['node/other']);
});

it('keeps reverse geocoded address and city when the provider has no address', function () {
    $this->osm->answer = [F::biz('node/1')];
    ($this->sync)();
    Prospect::first()->update(['address' => 'Jl. Hasil Geocode 5', 'address_source' => 'reverse_geocode', 'city' => 'Kota Hasil']);

    night(1);
    ($this->sync)();
    $p = Prospect::first();
    expect($p->address)->toBe('Jl. Hasil Geocode 5')->and($p->address_source)->toBe('reverse_geocode')->and($p->city)->toBe('Kota Hasil');

    night(2);
    $this->osm->answer = [F::biz('node/1', 300, 0, ['address' => 'Jl. Sumber 9'])];
    ($this->sync)();
    expect(Prospect::first()->address)->toBe('Jl. Sumber 9')->and(Prospect::first()->address_source)->toBe('source');
});

it('stamps google rows with a 30 day expiry and others without', function () {
    $google = new FakeProvider('google');
    $google->answer = [F::biz('ChIJ1', 300, 0, ['provider' => 'google'])];
    F::registry($google);
    DataSource::where('key', 'google')->update(['is_active' => true]);
    ($this->sync)();
    $p = Prospect::first();
    expect($p->provider)->toBe('google')->and($p->expires_at->toDateString())->toBe('2026-10-31')->and($p->needs_refresh)->toBeFalse();
});

it('builds the score input: simulated rating and phone only count on simulated rows', function () {
    $spy = new class implements ProspectScorer
    {
        /** @var list<ScoreInput> */
        public array $inputs = [];

        public function score(ScoreInput $input): ScoreResult
        {
            $this->inputs[] = $input;

            return new ScoreResult(55, [['key' => 'k', 'label' => 'L', 'value' => 1.0, 'max' => 10, 'points' => 5.0, 'reason' => 'r']]);
        }
    };
    app()->instance(ProspectScorer::class, $spy);

    $this->osm->answer = [
        F::biz('node/real-sim', 300, 0, ['rating' => 4.2, 'reviews' => 10, 'phone' => '0999-1111-2222', 'simulated' => ['phone', 'rating', 'review_count']]),
        F::biz('node/real', 400, 0, ['rating' => 4.2, 'reviews' => 10, 'phone' => '021555', 'website' => 'https://x.test', 'address' => 'Jl. A']),
    ];
    ($this->sync)();

    [$sim, $real] = $spy->inputs;
    expect($sim->rating)->toBe(4.2)->and($sim->ratingCounts)->toBeFalse()->and($sim->hasPhone)->toBeFalse()
        ->and($real->ratingCounts)->toBeTrue()->and($real->hasPhone)->toBeTrue()->and($real->hasWebsite)->toBeTrue()->and($real->hasAddress)->toBeTrue()
        ->and($real->categoryWeight)->toBe(80)->and($real->isNewOpen)->toBeFalse()->and($real->ageDays)->toBe(0)
        ->and($real->isClosed)->toBeFalse();
    $p = Prospect::where('place_id', 'node/real-sim')->first();
    expect($p->phone_simulated)->toBeTrue()->and($p->rating_simulated)->toBeTrue()->and($p->reviews_simulated)->toBeTrue()
        ->and($p->score)->toBe(55)->and($p->score_breakdown[0]['key'])->toBe('k');
});

it('counts simulated values on rows that are fully simulated (mock provider)', function () {
    $spy = new class implements ProspectScorer
    {
        public array $inputs = [];

        public function score(ScoreInput $input): ScoreResult
        {
            $this->inputs[] = $input;

            return new ScoreResult(10, []);
        }
    };
    app()->instance(ProspectScorer::class, $spy);
    $mock = new FakeProvider('mock');
    $mock->answer = [F::biz('mock/1', 300, 0, ['provider' => 'mock', 'rating' => 4.0, 'reviews' => 5, 'phone' => '0999-0000-0000', 'simulated' => ['phone', 'rating', 'review_count']])];
    F::registry($mock);

    ($this->sync)(null, [null, 'mock']);
    // sync(outlet, [ctx, onlyProvider])
    expect($spy->inputs)->toHaveCount(1)->and($spy->inputs[0]->ratingCounts)->toBeTrue()->and($spy->inputs[0]->hasPhone)->toBeTrue();
    expect(Prospect::first()->is_simulated)->toBeTrue();
});

it('reports a failure when no provider answers and records the data source error', function () {
    $this->osm->answer = new ProviderException('Overpass answered HTTP 504');
    $r = ($this->sync)();

    expect($r->status)->toBe('failed')->and($r->error)->toContain('504');
    expect(Prospect::count())->toBe(0);
    $src = DataSource::where('key', 'osm')->first();
    expect($src->last_status)->toBe('error')->and($src->last_error)->toContain('504');
    expect($this->outlet->fresh()->baseline_done_at)->toBeNull();
});

it('fails cleanly with no providers configured', function () {
    DataSource::query()->update(['is_active' => false]);
    $r = ($this->sync)();
    expect($r->status)->toBe('failed')->and($r->error)->toContain('No available');
});

it('merges providers only when sync.multi_provider is on', function () {
    $google = new FakeProvider('google');
    $osm = $this->osm;
    $osm->answer = [F::biz('node/1', 300, 0, ['name' => 'Kopi Senja Raya', 'address' => 'Jl. A'])];
    $google->answer = [
        F::biz('g1', 310, 0, ['provider' => 'google', 'name' => 'Kopi Senja Raya', 'phone' => '021777', 'rating' => 4.4]),
        F::biz('g2', 900, 0, ['provider' => 'google', 'name' => 'Apotek Lain', 'category' => 'bakery']),
    ];
    F::registry($osm, $google);
    DataSource::where('key', 'google')->update(['is_active' => true]);

    // single provider mode: google has priority 5 → answers first and wins
    ($this->sync)();
    expect($osm->calls)->toHaveCount(0)->and(Prospect::where('provider', 'google')->count())->toBe(2);

    Prospect::query()->delete();
    $this->outlet->forceFill(['baseline_done_at' => null])->save();
    Setting::write('sync.multi_provider', true);
    ($this->sync)();

    expect($osm->calls)->toHaveCount(1);
    // google is higher priority, so it keeps the merged business and osm's duplicate is merged into it
    $merged = Prospect::where('place_id', 'g1')->first();
    expect($merged)->not->toBeNull()->and($merged->address)->toBe('Jl. A')->and($merged->phone)->toBe('021777')
        ->and($merged->raw_tags['merged_from'])->toBe(['osm:node/1']);
    expect(Prospect::where('place_id', 'node/1')->exists())->toBeFalse();
    expect(Prospect::count())->toBe(2);
});
