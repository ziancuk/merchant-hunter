<?php

use App\Actions\ReconcileProspectsAction;
use App\DTO\ScoreInput;
use App\DTO\ScoreResult;
use App\Models\ApiCache;
use App\Models\Category;
use App\Models\GeocodeQueueItem;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Services\Scoring\ProspectScorer;
use App\Services\Sync\ScoreInputFactory;
use App\Services\Sync\WhitespaceCache;
use Illuminate\Support\Carbon;
use Tests\Feature\Sync\SyncFixtures as F;

beforeEach(function () {
    Carbon::setTestNow('2026-10-24 05:00:00');
    $this->w = F::world();
});

afterEach(fn () => Carbon::setTestNow());

function spyScorer(int $score = 66): object
{
    $spy = new class($score) implements ProspectScorer
    {
        /** @var list<ScoreInput> */
        public array $inputs = [];

        public function __construct(public int $score) {}

        public function score(ScoreInput $input): ScoreResult
        {
            $this->inputs[] = $input;

            return new ScoreResult($this->score, [['key' => 'k', 'label' => 'L', 'value' => 1.0, 'max' => 1, 'points' => 1.0, 'reason' => 'r']]);
        }
    };
    app()->instance(ProspectScorer::class, $spy);

    return $spy;
}

it('hunter:score rescored only unscored prospects by default and everything with --all', function () {
    $spy = spyScorer();
    $cat = $this->w['cafe'];
    $unscored = Prospect::factory()->create(['category_id' => $cat->id, 'score' => 0, 'score_breakdown' => null]);
    $scored = Prospect::factory()->create(['category_id' => $cat->id, 'score' => 40, 'score_breakdown' => [['key' => 'x']]]);

    $this->artisan('hunter:score')->assertSuccessful();
    expect($spy->inputs)->toHaveCount(1)->and($unscored->fresh()->score)->toBe(66)->and($scored->fresh()->score)->toBe(40);
    expect($spy->inputs[0]->categoryWeight)->toBe(80);

    $this->artisan('hunter:score', ['--all' => true])->assertSuccessful();
    expect($scored->fresh()->score)->toBe(66)->and($scored->fresh()->score_breakdown[0]['key'])->toBe('k');
});

it('hunter:score --all expires old "new open" flags, zeroes closed prospects and refreshes metrics', function () {
    spyScorer();
    $o = F::outlet($this->w['province'], 'OUT-1');
    [$lat, $lng] = F::at(300);
    [$lat2, $lng2] = F::at(420);
    $old = Prospect::factory()->create(['category_id' => $this->w['cafe']->id, 'lat' => $lat, 'lng' => $lng, 'is_new_open' => true, 'first_seen_at' => now()->subDays(31), 'nearest_outlet_id' => null, 'area_density' => 0]);
    $near = Prospect::factory()->create(['category_id' => $this->w['cafe']->id, 'lat' => $lat2, 'lng' => $lng2, 'is_new_open' => true, 'first_seen_at' => now()->subDays(3)]);
    $closed = Prospect::factory()->create(['category_id' => $this->w['cafe']->id, 'lifecycle_status' => 'closed', 'score' => 50]);
    $before = WhitespaceCache::version();

    $this->artisan('hunter:score', ['--all' => true])->assertSuccessful();

    expect($old->fresh()->is_new_open)->toBeFalse()->and($near->fresh()->is_new_open)->toBeTrue();
    $old->refresh();
    expect($old->nearest_outlet_id)->toBe($o->id)->and($old->nearest_outlet_distance_m)->toBeBetween(295, 305)
        ->and($old->neighbor_count_500m)->toBe(1)->and($old->area_density)->toBe(1)->and($old->province_id)->toBe($this->w['province']->id)
        ->and($old->city)->toBe('Jakarta Pusat');
    expect($closed->fresh()->score)->toBe(0)->and($closed->fresh()->score_breakdown)->toBeNull();
    expect(WhitespaceCache::version())->toBeGreaterThan($before);
});

it('hunter:score --all keeps reverse geocoded cities', function () {
    spyScorer();
    F::outlet($this->w['province'], 'OUT-1');
    [$lat, $lng] = F::at(300);
    $p = Prospect::factory()->create(['category_id' => $this->w['cafe']->id, 'lat' => $lat, 'lng' => $lng, 'address_source' => 'reverse_geocode', 'city' => 'Kota Geocode']);
    $this->artisan('hunter:score', ['--all' => true])->assertSuccessful();
    expect($p->fresh()->city)->toBe('Kota Geocode');
});

it('purge-expired clears expired Google content but keeps the place_id', function () {
    $cat = $this->w['cafe'];
    $expired = Prospect::factory()->create([
        'provider' => 'google', 'place_id' => 'ChIJexpired', 'category_id' => $cat->id, 'name' => 'Warung Rahasia', 'address' => 'Jl. X', 'phone' => '021',
        'website' => 'https://x.test', 'rating' => 4.5, 'review_count' => 10, 'opening_hours' => '8-5', 'raw_tags' => ['a' => 'b'],
        'expires_at' => now()->subDay(), 'is_simulated' => false,
    ]);
    $fresh = Prospect::factory()->create(['provider' => 'google', 'place_id' => 'ChIJfresh', 'category_id' => $cat->id, 'name' => 'Masih Berlaku', 'expires_at' => now()->addDays(10)]);
    $osm = Prospect::factory()->create(['provider' => 'osm', 'place_id' => 'node/1', 'category_id' => $cat->id, 'name' => 'OSM Row', 'expires_at' => null]);
    GeocodeQueueItem::create(['prospect_id' => $expired->id, 'priority' => 1, 'status' => 'pending', 'attempts' => 0, 'available_at' => now()]);
    ApiCache::create(['cache_key' => str_repeat('a', 64), 'provider' => 'osm', 'payload' => 'x', 'fetched_at' => now()->subDays(9), 'expires_at' => now()->subDay()]);
    ApiCache::create(['cache_key' => str_repeat('b', 64), 'provider' => 'osm', 'payload' => 'x', 'fetched_at' => now(), 'expires_at' => now()->addDay()]);

    $this->artisan('hunter:purge-expired')->expectsOutputToContain('1 expired Google prospect(s) and 1 expired cache row(s)')->assertSuccessful();

    $e = $expired->fresh();
    expect($e->place_id)->toBe('ChIJexpired')->and($e->provider)->toBe('google')->and($e->name)->toBe('[kedaluwarsa]')->and($e->needs_refresh)->toBeTrue()
        ->and($e->lat)->toBeNull()->and($e->lng)->toBeNull()->and($e->address)->toBeNull()->and($e->phone)->toBeNull()->and($e->website)->toBeNull()
        ->and($e->rating)->toBeNull()->and($e->review_count)->toBeNull()->and($e->opening_hours)->toBeNull()->and($e->raw_tags)->toBeNull();
    expect($fresh->fresh()->name)->toBe('Masih Berlaku')->and($fresh->fresh()->lat)->not->toBeNull()->and($fresh->fresh()->needs_refresh)->toBeFalse();
    expect($osm->fresh()->name)->toBe('OSM Row');
    expect(GeocodeQueueItem::count())->toBe(0)->and(ApiCache::count())->toBe(1);

    // idempotent: an already purged row is not touched twice
    $this->artisan('hunter:purge-expired')->expectsOutputToContain('0 expired Google prospect(s)')->assertSuccessful();
});

it('reconcile closes prospects that are in fact a newly imported outlet', function () {
    $cat = $this->w['cafe'];
    $outlet = F::outlet($this->w['province'], 'OUT-9', extra: ['name' => 'Kopi Senja Raya']);
    [$lat, $lng] = F::at(40);
    [$farLat, $farLng] = F::at(350);
    $match = Prospect::factory()->create(['category_id' => $cat->id, 'name' => 'Toko Kopi Senja Raya', 'lat' => $lat, 'lng' => $lng, 'is_new_open' => true, 'score' => 77]);
    $different = Prospect::factory()->create(['category_id' => $cat->id, 'name' => 'Bengkel Maju', 'lat' => $lat, 'lng' => $lng]);
    $far = Prospect::factory()->create(['category_id' => $cat->id, 'name' => 'Kopi Senja Raya', 'lat' => $farLat, 'lng' => $farLng]);
    $alreadyClosed = Prospect::factory()->create(['category_id' => $cat->id, 'name' => 'Kopi Senja Raya', 'lat' => $lat, 'lng' => $lng, 'lifecycle_status' => 'closed']);
    $before = WhitespaceCache::version();

    $closed = app(ReconcileProspectsAction::class)->execute($outlet);

    expect($closed)->toBe(1);
    $m = $match->fresh();
    expect($m->lifecycle_status->value)->toBe('closed')->and($m->closed_reason)->toBe('hitachi_merchant')->and($m->closed_at)->not->toBeNull()
        ->and($m->score)->toBe(0)->and($m->is_new_open)->toBeFalse();
    expect($different->fresh()->lifecycle_status->value)->toBe('open')->and($far->fresh()->lifecycle_status->value)->toBe('open');
    expect($alreadyClosed->fresh()->closed_reason)->toBeNull();

    $activity = ProspectActivity::sole();
    expect($activity->prospect_id)->toBe($match->id)->and($activity->user_id)->toBeNull()->and($activity->type->value)->toBe('system')
        ->and($activity->body)->toContain('Kopi Senja Raya')->and($activity->meta['reason'])->toBe('hitachi_merchant');
    expect(WhitespaceCache::version())->toBeGreaterThan($before);
});

it('reconcile accepts several outlets, ignores unlocated ones and is invokable', function () {
    $cat = $this->w['cafe'];
    $a = F::outlet($this->w['province'], 'A', extra: ['name' => 'Alfa Mart']);
    $b = F::outlet($this->w['province'], 'B', -6.3, 106.9, ['name' => 'Beta Store']);
    $nowhere = Outlet::factory()->create(['code' => 'C', 'lat' => null, 'lng' => null]);
    [$lat, $lng] = F::at(10);
    Prospect::factory()->create(['category_id' => $cat->id, 'name' => 'Alfa Mart', 'lat' => $lat, 'lng' => $lng]);
    Prospect::factory()->create(['category_id' => $cat->id, 'name' => 'Beta Store', 'lat' => -6.3001, 'lng' => 106.9001]);

    expect((new ReconcileProspectsAction)([$a, $b, $nowhere]))->toBe(2);
    expect(Prospect::where('closed_reason', 'hitachi_merchant')->count())->toBe(2);
    expect(app(ReconcileProspectsAction::class)->execute([]))->toBe(0);
    // second call: nothing left open to close
    expect(app(ReconcileProspectsAction::class)->execute(Outlet::all()))->toBe(0);
});

it('score input factory covers null and closed attributes', function () {
    $input = ScoreInputFactory::fromAttributes(['lifecycle_status' => 'closed', 'is_simulated' => true, 'rating' => 4, 'rating_simulated' => true, 'phone' => '0999', 'phone_simulated' => true], 70, now());
    expect($input->isClosed)->toBeTrue()->and($input->ratingCounts)->toBeTrue()->and($input->hasPhone)->toBeTrue()
        ->and($input->reviewCount)->toBeNull()->and($input->nearestOutletDistanceM)->toBeNull()->and($input->ageDays)->toBe(0);
    expect(Category::count())->toBe(2);
});
