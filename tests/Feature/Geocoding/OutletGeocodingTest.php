<?php

use App\Jobs\GeocodeOutletJob;
use App\Models\Outlet;
use App\Models\Province;
use App\Services\Geocoding\GeocoderInterface;
use App\Services\Geocoding\GeocodingBlockedException;
use App\Services\Geocoding\NullGeocoder;
use Tests\Feature\Geocoding\FakeGeocoder;

function pendingOutlet(string $code, array $extra = []): Outlet
{
    return Outlet::factory()->create(array_merge([
        'code' => $code, 'name' => 'Merchant '.$code, 'city' => 'Bandung', 'lat' => null, 'lng' => null,
        'geocode_source' => 'csv', 'geocode_status' => 'ok',
        'province_id' => Province::factory()->create(['lat' => -6.9, 'lng' => 107.6])->id,
    ], $extra));
}

beforeEach(function () {
    config(['hunter.geocoding.enabled' => true]);
    $this->geo = FakeGeocoder::install();
});

it('auto-accepts a match of 80 or more and stores the candidates', function () {
    $o = pendingOutlet('A');
    $this->geo->forwardAnswer = [
        ['display_name' => 'Merchant A, Bandung', 'lat' => -6.91, 'lng' => 107.61, 'score' => 92],
        ['display_name' => 'Other', 'lat' => -6.95, 'lng' => 107.7, 'score' => 40],
    ];

    $this->artisan('hunter:geocode-outlets')->assertSuccessful();

    $o->refresh();
    expect($o->lat)->toBe(-6.91)->and($o->lng)->toBe(107.61)->and($o->geocode_status->value)->toBe('ok')
        ->and($o->geocode_score)->toBe(92)->and($o->geocode_source)->toBe('nominatim')
        ->and($o->geocode_candidates)->toHaveCount(2);
    expect($this->geo->forwardCalls[0])->toBe(['Merchant A', 'Bandung', -6.9, 107.6]);
});

it('sends 50-79 to review without coordinates and rejects below 50', function () {
    $review = pendingOutlet('R');
    $bad = pendingOutlet('B');
    $none = pendingOutlet('N');

    $answers = [
        'R' => [['display_name' => 'Maybe', 'lat' => -6.9, 'lng' => 107.6, 'score' => 65]],
        'B' => [['display_name' => 'Nope', 'lat' => -6.9, 'lng' => 107.6, 'score' => 30]],
        'N' => [],
    ];
    $geo = new class($answers) extends FakeGeocoder
    {
        public function __construct(private array $answers) {}

        public function forward(string $name, string $city, ?float $nearLat = null, ?float $nearLng = null): array
        {
            return $this->answers[substr($name, -1)];
        }
    };
    app()->instance(GeocoderInterface::class, $geo);

    $this->artisan('hunter:geocode-outlets')->expectsOutputToContain('3 outlet(s): 0 placed, 1 to review, 2 failed')->assertSuccessful();

    expect($review->fresh()->geocode_status->value)->toBe('needs_review')->and($review->fresh()->lat)->toBeNull()
        ->and($review->fresh()->geocode_candidates)->toHaveCount(1)->and($review->fresh()->geocode_score)->toBe(65);
    expect($bad->fresh()->geocode_status->value)->toBe('failed')->and($bad->fresh()->geocode_score)->toBe(30);
    expect($none->fresh()->geocode_status->value)->toBe('failed')->and($none->fresh()->geocode_score)->toBeNull()
        ->and($none->fresh()->geocode_candidates)->toBe([]);
});

it('only picks outlets that wait for coordinates and are not manual, failed or in review', function () {
    pendingOutlet('OK1');
    pendingOutlet('MAN', ['geocode_status' => 'manual']);
    pendingOutlet('FAIL', ['geocode_status' => 'failed']);
    pendingOutlet('REV', ['geocode_status' => 'needs_review']);
    Outlet::factory()->create(['code' => 'HAS', 'lat' => -6.2, 'lng' => 106.8]);
    $this->geo->forwardAnswer = [];

    $this->artisan('hunter:geocode-outlets')->assertSuccessful();
    expect($this->geo->forwardCalls)->toHaveCount(1);

    $this->artisan('hunter:geocode-outlets', ['--limit' => 1])->assertSuccessful();
});

it('stops when the geocoder is blocked and leaves the outlet untouched', function () {
    $o = pendingOutlet('A');
    $this->geo->forwardAnswer = new GeocodingBlockedException('429');
    $this->artisan('hunter:geocode-outlets')->expectsOutputToContain('Stopped')->assertSuccessful();
    expect($o->fresh()->geocode_source)->toBe('csv')->and($o->fresh()->lat)->toBeNull();
});

it('is a no-op when geocoding is disabled or unbound', function () {
    pendingOutlet('A');
    config(['hunter.geocoding.enabled' => false]);
    $this->artisan('hunter:geocode-outlets')->assertSuccessful();
    config(['hunter.geocoding.enabled' => true]);
    app()->instance(GeocoderInterface::class, new NullGeocoder);
    $this->artisan('hunter:geocode-outlets')->assertSuccessful();
    expect(Outlet::first()->geocode_source)->toBe('csv');
});

it('geocodes one outlet through GeocodeOutletJob (also for outlets in review)', function () {
    $o = pendingOutlet('J', ['geocode_status' => 'failed']);
    $this->geo->forwardAnswer = [['display_name' => 'Merchant J', 'lat' => -6.93, 'lng' => 107.62, 'score' => 88]];

    dispatch(new GeocodeOutletJob($o));
    expect($o->fresh()->geocode_status->value)->toBe('ok')->and($o->fresh()->lat)->toBe(-6.93);

    dispatch(new GeocodeOutletJob(99999)); // unknown outlet: no-op
    app()->instance(GeocoderInterface::class, new NullGeocoder);
    dispatch(new GeocodeOutletJob($o->id));
    expect($this->geo->forwardCalls)->toHaveCount(1);
});
