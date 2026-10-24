<?php

use App\Services\Geocoding\AddressFormatter;
use App\Services\Geocoding\GeocodingBlockedException;
use App\Services\Geocoding\GeocodingException;
use App\Services\Geocoding\MatchScorer;
use App\Services\Geocoding\NominatimGeocoder;
use App\Services\Geocoding\NominatimRateLimiter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Providers\FakeSleeper;

function nominatimFake(array|Closure $stubs): void
{
    Http::swap(new Factory);
    Http::fake($stubs);
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-24 08:00:00');
    Cache::flush();
    config(['hunter.geocoding.enabled' => true, 'hunter.geocoding.nominatim_url' => 'https://nominatim.test', 'hunter.overpass.contact_email' => 'ops@example.test']);
    $this->sleeper = FakeSleeper::install();
});

afterEach(fn () => Carbon::setTestNow());

function reverseSample(): array
{
    return [
        'place_id' => 1, 'lat' => '-6.2', 'lon' => '106.8', 'display_name' => 'Raw display name',
        'address' => [
            'house_number' => '12', 'road' => 'Jalan Merdeka', 'neighbourhood' => 'Menteng', 'suburb' => 'Menteng',
            'city_district' => 'Kecamatan Menteng', 'city' => 'Jakarta Pusat', 'state' => 'DKI Jakarta', 'postcode' => '10310', 'country' => 'Indonesia',
        ],
    ];
}

it('reverse geocodes with the documented parameters and builds a one-line Indonesian address', function () {
    nominatimFake(['nominatim.test/*' => Http::response(reverseSample())]);

    $g = app(NominatimGeocoder::class);
    expect($g->reverse(-6.2, 106.8))->toBe('Jalan Merdeka No. 12, Menteng, Kecamatan Menteng, Jakarta Pusat, DKI Jakarta, 10310');

    Http::assertSent(function (Request $r) {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

        return str_starts_with($r->url(), 'https://nominatim.test/reverse?')
            && $q['format'] === 'jsonv2' && $q['zoom'] === '18' && $q['addressdetails'] === '1' && $q['accept-language'] === 'id'
            && (float) $q['lat'] === -6.2 && (float) $q['lon'] === 106.8
            && $r->header('User-Agent')[0] === 'pos-merchant-hunter/1.0 (ops@example.test)';
    });
});

it('returns the city with the detailed reverse result', function () {
    nominatimFake(['nominatim.test/*' => Http::response(reverseSample())]);
    $d = app(NominatimGeocoder::class)->reverseDetailed(-6.2, 106.8);
    expect($d['city'])->toBe('Jakarta Pusat')->and($d['address'])->toContain('Jalan Merdeka');
});

it('returns null when Nominatim finds nothing or the feature flag is off', function () {
    nominatimFake(['nominatim.test/*' => Http::response(['error' => 'Unable to geocode'])]);
    expect(app(NominatimGeocoder::class)->reverse(0.0, 0.0))->toBeNull();

    config(['hunter.geocoding.enabled' => false]);
    nominatimFake(fn () => throw new RuntimeException('must not be called'));
    $g = app(NominatimGeocoder::class);
    expect($g->enabled())->toBeFalse()->and($g->reverse(-6.2, 106.8))->toBeNull()->and($g->forward('Kopi', 'Jakarta'))->toBe([]);
});

it('falls back to display_name when no structured address is usable', function () {
    expect(AddressFormatter::format(['display_name' => 'Somewhere, Indonesia', 'address' => []]))->toBe('Somewhere, Indonesia');
    expect(AddressFormatter::format(['address' => []]))->toBeNull();
    expect(AddressFormatter::format(['address' => ['town' => 'Ubud', 'village' => 'ubud', 'county' => 'Gianyar']]))->toBe('ubud, Gianyar');
    expect(AddressFormatter::city(['address' => ['regency' => 'Kabupaten Bogor']]))->toBe('Kabupaten Bogor');
    expect(AddressFormatter::city([]))->toBeNull();
});

it('forward geocodes with q, countrycodes, limit and a bounded viewbox, best match first', function () {
    nominatimFake(['nominatim.test/*' => Http::response([
        ['lat' => '-6.3000', 'lon' => '106.9000', 'category' => 'highway', 'importance' => 0.1, 'display_name' => 'Jalan Jauh, Bekasi', 'name' => 'Jalan Jauh'],
        ['lat' => '-6.2001', 'lon' => '106.8001', 'category' => 'amenity', 'importance' => 0.5, 'display_name' => 'Kopi Senja, Jalan Merdeka, Jakarta', 'name' => 'Kopi Senja'],
    ])]);

    $r = app(NominatimGeocoder::class)->forward('Kopi Senja', 'Jakarta Pusat', -6.2, 106.8);

    expect($r)->toHaveCount(2)->and($r[0]['display_name'])->toContain('Kopi Senja')->and($r[0]['lat'])->toBe(-6.2001)
        ->and($r[0]['score'])->toBeGreaterThanOrEqual(80)->and($r[1]['score'])->toBeLessThan(50);
    Http::assertSent(function (Request $req) {
        parse_str((string) parse_url($req->url(), PHP_URL_QUERY), $q);

        return str_starts_with($req->url(), 'https://nominatim.test/search?')
            && $q['q'] === 'Kopi Senja, Jakarta Pusat' && $q['countrycodes'] === 'id' && $q['limit'] === '5'
            && $q['bounded'] === '1' && count(explode(',', $q['viewbox'])) === 4 && $q['format'] === 'jsonv2';
    });
});

it('omits the viewbox without a reference point and skips malformed results', function () {
    nominatimFake(['nominatim.test/*' => Http::response([['display_name' => 'no coords'], 'junk'])]);
    expect(app(NominatimGeocoder::class)->forward('Kopi', 'Jakarta'))->toBe([]);
    Http::assertSent(fn (Request $r) => ! str_contains($r->url(), 'viewbox'));
});

it('scores matches per D-45 and classifies them', function () {
    $near = ['name' => 'Kopi Senja', 'lat' => '-6.2', 'lon' => '106.8', 'category' => 'amenity', 'importance' => 1.0];
    expect(MatchScorer::score('Kopi Senja', $near, -6.2, 106.8))->toBe(100);

    // 10 km away => distance score 0.5; name equal; no importance; unknown class
    $far = ['name' => 'Kopi Senja', 'lat' => '-6.2', 'lon' => '106.8905', 'display_name' => 'x'];
    $score = MatchScorer::score('Kopi Senja', $far, -6.2, 106.8);
    expect($score)->toBeBetween(70, 76);

    expect(MatchScorer::score('Kopi Senja', ['display_name' => 'Kopi Senja, Jakarta', 'lat' => '-6.2', 'lon' => '106.8'], null, null))->toBeGreaterThanOrEqual(70);
    expect(MatchScorer::classify(80))->toBe('ok')->and(MatchScorer::classify(79))->toBe('needs_review')
        ->and(MatchScorer::classify(50))->toBe('needs_review')->and(MatchScorer::classify(49))->toBe('failed');
});

it('shares one 4 requests/minute limit between reverse and forward calls', function () {
    nominatimFake(['nominatim.test/reverse*' => Http::response(reverseSample()), 'nominatim.test/search*' => Http::response([])]);
    $g = app(NominatimGeocoder::class);

    $g->reverse(-6.2, 106.8);
    expect($this->sleeper->sleeps)->toBe([]);
    $g->forward('Kopi', 'Jakarta');
    expect($this->sleeper->sleeps)->toHaveCount(1)->and($this->sleeper->sleeps[0])->toBeGreaterThan(14.9)->toBeLessThanOrEqual(15.0);
    $g->reverse(-6.21, 106.81);
    expect($this->sleeper->sleeps)->toHaveCount(2)->and($this->sleeper->sleeps[1])->toBeGreaterThan(14.9);

    // After a quiet minute the next request goes straight through.
    Carbon::setTestNow(Carbon::now()->addMinute());
    $g->reverse(-6.22, 106.82);
    expect($this->sleeper->sleeps)->toHaveCount(2);
    expect(app(NominatimRateLimiter::class)->interval())->toBe(15.0);
});

it('stops on 429 and 403 and refuses further requests while blocked', function () {
    nominatimFake(['nominatim.test/*' => Http::response('Too many', 429)]);
    $g = app(NominatimGeocoder::class);

    expect(fn () => $g->reverse(-6.2, 106.8))->toThrow(GeocodingBlockedException::class, '429');
    expect(app(NominatimRateLimiter::class)->blocked())->toBeTrue();

    $sent = count(Http::recorded());
    expect(fn () => $g->forward('Kopi', 'Jakarta'))->toThrow(GeocodingBlockedException::class, 'paused');
    expect(count(Http::recorded()))->toBe($sent);

    Carbon::setTestNow(Carbon::now()->addMinutes(61));
    expect(app(NominatimRateLimiter::class)->blocked())->toBeFalse();

    nominatimFake(['nominatim.test/*' => Http::response('Forbidden', 403)]);
    expect(fn () => $g->reverse(-6.2, 106.8))->toThrow(GeocodingBlockedException::class, '403');
});

it('raises a transient error on server errors, connection failures and bad JSON', function () {
    $g = app(NominatimGeocoder::class);
    nominatimFake(['nominatim.test/*' => Http::response('', 503)]);
    expect(fn () => $g->reverse(-6.2, 106.8))->toThrow(GeocodingException::class, '503');
    expect(app(NominatimRateLimiter::class)->blocked())->toBeFalse();

    nominatimFake(['nominatim.test/*' => Http::response('<html>', 200)]);
    expect(fn () => $g->reverse(-6.2, 106.8))->toThrow(GeocodingException::class, 'invalid JSON');

    nominatimFake(fn () => throw new ConnectionException('timeout'));
    expect(fn () => $g->reverse(-6.2, 106.8))->toThrow(GeocodingException::class, 'connection failed');
});
