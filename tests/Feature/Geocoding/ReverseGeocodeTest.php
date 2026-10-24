<?php

use App\Jobs\GeocodeProspectJob;
use App\Models\GeocodeQueueItem;
use App\Models\Prospect;
use App\Models\SchedulerRun;
use App\Services\Geocoding\GeocoderInterface;
use App\Services\Geocoding\GeocodingBlockedException;
use App\Services\Geocoding\GeocodingException;
use App\Services\Geocoding\NominatimGeocoder;
use App\Services\Geocoding\NullGeocoder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Geocoding\FakeGeocoder;
use Tests\Feature\Providers\FakeSleeper;

function queued(int $priority, array $prospect = [], array $item = []): GeocodeQueueItem
{
    $p = Prospect::factory()->create(array_merge(['address' => null, 'address_source' => 'none', 'city' => 'Kota Outlet'], $prospect));

    return GeocodeQueueItem::create(array_merge([
        'prospect_id' => $p->id, 'priority' => $priority, 'status' => 'pending', 'attempts' => 0, 'available_at' => now()->subMinute(),
    ], $item));
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-24 08:00:00');
    Cache::flush();
    config(['hunter.geocoding.enabled' => true, 'hunter.geocoding.max_per_run' => 100]);
    $this->geo = FakeGeocoder::install();
});

afterEach(fn () => Carbon::setTestNow());

it('fills addresses in priority order and records a scheduler run', function () {
    $low = queued(10);
    $high = queued(90);
    $mid = queued(50);

    $this->artisan('hunter:geocode')->assertSuccessful();

    expect(array_column($this->geo->reverseCalls, 0))->toBe([
        (float) $high->prospect->lat, (float) $mid->prospect->lat, (float) $low->prospect->lat,
    ]);
    $p = $high->prospect->fresh();
    expect($p->address)->toBe('Jl. Hasil 1, Jakarta')->and($p->address_source)->toBe('reverse_geocode')->and($p->city)->toBe('Jakarta Pusat');
    $item = $high->fresh();
    expect($item->status)->toBe('done')->and($item->done_at)->not->toBeNull()->and($item->attempts)->toBe(1);

    $run = SchedulerRun::first();
    expect($run->command)->toBe('hunter:geocode')->and($run->status)->toBe('success')->and($run->prospects_updated)->toBe(3);
});

it('processes at most max_per_run items', function () {
    config(['hunter.geocoding.max_per_run' => 2]);
    foreach ([30, 20, 10] as $p) {
        queued($p);
    }
    $this->artisan('hunter:geocode')->assertSuccessful();
    expect($this->geo->reverseCalls)->toHaveCount(2)->and(GeocodeQueueItem::where('status', 'pending')->count())->toBe(1);

    $this->artisan('hunter:geocode', ['--max' => 1])->assertSuccessful();
    expect(GeocodeQueueItem::where('status', 'pending')->count())->toBe(0);
});

it('keeps the outlet-derived city when the geocoder gives none', function () {
    $this->geo->reverseAnswer = ['address' => 'Jl. Tanpa Kota', 'city' => null];
    $item = queued(5);
    $this->artisan('hunter:geocode')->assertSuccessful();
    expect($item->prospect->fresh()->city)->toBe('Kota Outlet');
});

it('retries empty results the next day and fails the item after 3 attempts', function () {
    $this->geo->reverseAnswer = null;
    $item = queued(5);

    $this->artisan('hunter:geocode')->assertSuccessful();
    $item->refresh();
    expect($item->status)->toBe('pending')->and($item->attempts)->toBe(1)->and($item->last_error)->toBe('No address found')
        ->and($item->available_at->toDateString())->toBe('2026-10-25');

    // not due yet: nothing happens
    $this->artisan('hunter:geocode')->assertSuccessful();
    expect($this->geo->reverseCalls)->toHaveCount(1);

    foreach ([25, 26] as $day) {
        Carbon::setTestNow("2026-10-{$day} 08:00:00");
        $this->artisan('hunter:geocode')->assertSuccessful();
    }
    $item->refresh();
    expect($item->status)->toBe('failed')->and($item->attempts)->toBe(3);
    expect($item->prospect->fresh()->address)->toBeNull();
});

it('stops the run and leaves items untouched when Nominatim blocks us', function () {
    $this->geo->reverseAnswer = new GeocodingBlockedException('429');
    $a = queued(90);
    $b = queued(80);

    $this->artisan('hunter:geocode')->assertSuccessful();

    expect($this->geo->reverseCalls)->toHaveCount(1);
    expect($a->fresh()->status)->toBe('pending')->and($a->fresh()->attempts)->toBe(0)->and($b->fresh()->attempts)->toBe(0);
    expect(SchedulerRun::first()->status)->toBe('failed')->and(SchedulerRun::first()->error)->toContain('blocked');
});

it('stops after 3 consecutive transient errors and records attempts', function () {
    $this->geo->reverseAnswer = new GeocodingException('connection failed');
    foreach ([50, 40, 30, 20] as $p) {
        queued($p);
    }
    $this->artisan('hunter:geocode')->assertSuccessful();
    expect($this->geo->reverseCalls)->toHaveCount(3);
    expect(GeocodeQueueItem::where('attempts', 1)->count())->toBe(3)
        ->and(GeocodeQueueItem::where('attempts', 1)->first()->last_error)->toBe('connection failed');
});

it('does nothing when the feature flag is off, no geocoder is bound, or the queue is empty', function () {
    queued(5);
    config(['hunter.geocoding.enabled' => false]);
    $this->artisan('hunter:geocode')->expectsOutputToContain('disabled')->assertSuccessful();
    expect($this->geo->reverseCalls)->toBe([]);

    config(['hunter.geocoding.enabled' => true]);
    app()->instance(GeocoderInterface::class, new NullGeocoder);
    $this->artisan('hunter:geocode')->expectsOutputToContain('No geocoder')->assertSuccessful();
    expect(GeocodeQueueItem::first()->status)->toBe('pending');

    FakeGeocoder::install();
    GeocodeQueueItem::query()->delete();
    $this->artisan('hunter:geocode')->expectsOutputToContain('empty')->assertSuccessful();
    expect(SchedulerRun::count())->toBe(0);
});

it('marks items done when the prospect already has an address and failed when it has no coordinates', function () {
    $has = queued(5, ['address' => 'Sudah ada']);
    $gone = queued(4, ['lat' => null, 'lng' => null]);

    $this->artisan('hunter:geocode')->assertSuccessful();

    expect($this->geo->reverseCalls)->toBe([]);
    expect($has->fresh()->status)->toBe('done')->and($gone->fresh()->status)->toBe('failed')->and($gone->fresh()->last_error)->toContain('coordinates');
});

it('honours the shared 4 requests/minute limit with the real Nominatim geocoder', function () {
    $sleeper = FakeSleeper::install();
    config(['hunter.geocoding.nominatim_url' => 'https://nominatim.test']);
    Http::fake(['nominatim.test/*' => Http::response(['address' => ['road' => 'Jalan Uji', 'city' => 'Jakarta']])]);
    app()->instance(GeocoderInterface::class, app(NominatimGeocoder::class));
    foreach ([30, 20, 10] as $p) {
        queued($p);
    }

    $this->artisan('hunter:geocode')->assertSuccessful();

    Http::assertSentCount(3);
    expect($sleeper->sleeps)->toHaveCount(2)->and($sleeper->sleeps[0])->toBeGreaterThan(14.9)->and($sleeper->sleeps[1])->toBeGreaterThan(14.9);
    expect(Prospect::where('address_source', 'reverse_geocode')->count())->toBe(3)
        ->and(Prospect::first()->address)->toBe('Jalan Uji, Jakarta');
});

it('geocodes a single prospect through the job', function () {
    $item = queued(5);
    $other = queued(5);
    dispatch(new GeocodeProspectJob($item->prospect));

    expect($item->fresh()->status)->toBe('done')->and($other->fresh()->status)->toBe('pending')->and($this->geo->reverseCalls)->toHaveCount(1);

    // already done / unknown prospect / geocoder missing: no-ops
    dispatch(new GeocodeProspectJob($item->prospect_id));
    dispatch(new GeocodeProspectJob(99999));
    app()->instance(GeocoderInterface::class, new NullGeocoder);
    dispatch(new GeocodeProspectJob($other->prospect_id));
    expect($other->fresh()->status)->toBe('pending')->and($this->geo->reverseCalls)->toHaveCount(1);
});

it('works with a geocoder that only implements the plain interface', function () {
    $plain = new class implements GeocoderInterface
    {
        public function reverse(float $lat, float $lng): ?string
        {
            return 'Plain Address';
        }

        public function forward(string $name, string $city, ?float $nearLat = null, ?float $nearLng = null): array
        {
            return [];
        }
    };
    app()->instance(GeocoderInterface::class, $plain);
    $item = queued(5);
    $this->artisan('hunter:geocode')->assertSuccessful();
    expect($item->prospect->fresh()->address)->toBe('Plain Address')->and($item->prospect->fresh()->city)->toBe('Kota Outlet');
});
