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

it('extracts a street from a messy address', function (string $address, ?string $street) {
    expect(App\Services\Geocoding\OutletGeocoder::street($address))->toBe($street);
})->with([
    ['Jl. Pramuka No. 5, Kec. Laweyan, Kota Surakarta', 'Jalan Pramuka'],
    ['Jl.Brajaya, Sleman', 'Jalan Brajaya'],
    ['Jl. Jend. Sudirman No.Kav 40-41, RT.9/RW.1', 'Jalan Jenderal Sudirman'],
    ['Gg. Melati 3 Blok A', 'Gang Melati 3'],
    ['Jl. Lingkaran1 No.454, 15 Ilir', 'Jalan Lingkaran 1'],
    ['Jl Ap Pettarani ruko bosnis center B25', 'Jalan Ap Pettarani'],
    ['Jl. Tj. Duren Bar. 2 No. 16 A', 'Jalan Tanjung Duren Bar. 2'],
    ['Jl. Angkasa Kavling B6 MGK, Lantai GF', 'Jalan Angkasa'],
    ['Sahid Sudirman Residence LG 10A OFF, Jl. Jenderal Sudirman No.86, RT.10', 'Jalan Jenderal Sudirman'],
    ['Kota Jakarta, Kec. Cilandak', null],
    ['Pasar Santa Lantai 1', null],
    ['.', null],
    ['', null],
]);

it('falls back to the street from the address when the name is not found, flagging the pin as approximate', function () {
    $o = pendingOutlet('S', ['address' => 'Jl. Pramuka No. 5, Kec. X']);
    $this->geo->forwardAnswer = [];
    $this->geo->streetAnswer = ['display_name' => 'Jalan Pramuka, Bandung', 'lat' => -6.92, 'lng' => 107.62];

    $this->artisan('hunter:geocode-outlets')->assertSuccessful();

    $o->refresh();
    expect($o->lat)->toBe(-6.92)->and($o->geocode_status->value)->toBe('ok')->and($o->geocode_source)->toBe('nominatim_street')
        ->and($this->geo->streetCalls)->toBe([['Jalan Pramuka', 'Bandung']]);
});

it('stays failed when the street is not found either, or the address has no street', function () {
    $miss = pendingOutlet('M', ['address' => 'Jl. Tidak Ada']);
    $none = pendingOutlet('X', ['address' => 'Pasar Santa Lantai 1']);

    $this->artisan('hunter:geocode-outlets')->assertSuccessful();

    expect($miss->refresh()->geocode_status->value)->toBe('failed')->and($none->refresh()->geocode_status->value)->toBe('failed')
        ->and($this->geo->streetCalls)->toHaveCount(1);
});

it('re-tries failed outlets with --retry-failed, name search included', function () {
    $o = pendingOutlet('F', ['address' => 'Jl. Pramuka 1', 'geocode_status' => 'failed']);
    $this->geo->streetAnswer = ['display_name' => 'Jalan Pramuka', 'lat' => -6.9, 'lng' => 107.6];

    $this->artisan('hunter:geocode-outlets', ['--retry-failed' => true])->assertSuccessful();

    expect($o->refresh()->geocode_status->value)->toBe('ok')->and($this->geo->forwardCalls)->toHaveCount(1);
});

it('loosens the name by dropping branch words, the city and a trailing number', function (string $name, string $city, ?string $loose) {
    expect(App\Services\Geocoding\OutletGeocoder::looseName($name, $city))->toBe($loose);
})->with([
    ['I Am Geprek Bensu Palembang', 'PALEMBANG', 'I Am Geprek Bensu'],
    ['PSI Cabang Lampung', 'KOTA BANDAR LAMPUNG', null],
    ['Djon Seafood 2', 'BANTUL', 'Djon Seafood'],
    ['Kopi Outlet Surabaya (Cabang Tunjungan)', 'Kota Surabaya', null],
    ['Merchant A', 'Bandung', null],
]);

it('searches again with the loosened name when the full name finds nothing usable', function () {
    $o = pendingOutlet('L2', ['name' => 'Geprek Bensu Bandung']);
    $geo = new class extends FakeGeocoder
    {
        public function forward(string $name, string $city, ?float $nearLat = null, ?float $nearLng = null): array
        {
            $this->forwardCalls[] = [$name, $city];

            return $name === 'Geprek Bensu' ? [['display_name' => 'Geprek Bensu, Bandung', 'lat' => -6.91, 'lng' => 107.61, 'score' => 88]] : [];
        }
    };
    app()->instance(GeocoderInterface::class, $geo);

    $this->artisan('hunter:geocode-outlets')->assertSuccessful();

    expect($o->refresh()->geocode_status->value)->toBe('ok')->and($o->lat)->toBe(-6.91)->and($o->geocode_source)->toBe('nominatim')
        ->and($geo->forwardCalls)->toBe([['Geprek Bensu Bandung', 'Bandung'], ['Geprek Bensu', 'Bandung']]);
});

it('extracts a landmark and a kecamatan from an address', function () {
    $g = App\Services\Geocoding\OutletGeocoder::class;
    expect($g::landmark('Kota Kasablanka Mall. Barang yang sudah dibeli'))->toBe('Kota Kasablanka Mall')
        ->and($g::landmark('Thamrin City Lantai Dasar 1 Blok A10, Kebon Melati'))->toBe('Thamrin City')
        ->and($g::landmark('Jl. Pramuka No. 5'))->toBeNull()
        ->and($g::landmark('Menara BCA'))->toBe('Menara BCA')
        ->and($g::landmark('Bandung'))->toBeNull()->and($g::landmark('Pucang Anom Timur no. 41'))->toBeNull()
        ->and($g::area('G Walk, Kecamatan Jl. Citraland, Lidah Kulon, Kec. Lakarsantri, Surabaya'))->toBe('Kecamatan Lakarsantri')
        ->and($g::area('Jl. A, Kel. Sukabumi Selatan, Jakarta'))->toBe('Kelurahan Sukabumi Selatan')
        ->and($g::area('Jl. A, Kec. Pd'))->toBeNull();
});

it('falls through street, landmark and area, flagging each source', function () {
    $o = pendingOutlet('L', ['address' => 'Kota Kasablanka Mall. Barang tidak dapat dikembalikan']);
    $this->geo->placeAnswers['landmark'] = ['display_name' => 'Kota Kasablanka, Jakarta Selatan', 'lat' => -6.22, 'lng' => 106.84];
    $a = pendingOutlet('K', ['address' => 'Jl. Panjang, Kec. Serpong Utara']);
    $this->geo->placeAnswers['area'] = ['display_name' => 'Serpong Utara', 'lat' => -6.25, 'lng' => 106.67];

    $this->artisan('hunter:geocode-outlets')->assertSuccessful();

    expect($o->refresh()->geocode_source)->toBe('nominatim_landmark')->and($o->lat)->toBe(-6.22)
        ->and($a->refresh()->geocode_source)->toBe('nominatim_area')->and($a->lat)->toBe(-6.25);
});
