<?php

use App\DTO\OutletPoint;
use App\Models\ApiCache;
use App\Models\Category;
use App\Models\DataSource;
use App\Services\Providers\Osm\OverpassClient;
use App\Services\Providers\Osm\OverpassQuery;
use App\Services\Providers\OsmProvider;
use App\Services\Providers\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Providers\FakeSleeper;

/** Replace (not append to) the Http fake so a later stub wins. */
function refake(array|Closure $stubs): void
{
    Http::swap(new Factory);
    Http::fake($stubs);
}

const OSM_PRIMARY = 'overpass-api.de/*';
const OSM_MIRROR = 'overpass.private.coffee/*';

beforeEach(function () {
    Carbon::setTestNow('2026-10-24 01:00:00');
    Cache::flush();
    $this->sleeper = FakeSleeper::install();
    DataSource::create(['key' => 'osm', 'name' => 'OSM', 'is_active' => true, 'priority' => 10]);
    $this->cafe = Category::factory()->create(['key' => 'cafe', 'osm_filters' => ['amenity' => ['cafe']]]);
    $this->bakery = Category::factory()->create(['key' => 'bakery', 'osm_filters' => ['shop' => ['bakery']]]);
    $this->categories = [$this->cafe, $this->bakery];
    $this->center = new OutletPoint(1, 'OUT-1', 'Outlet', -6.2, 106.8);
});

afterEach(fn () => Carbon::setTestNow());

function overpassSample(): array
{
    return ['version' => 0.6, 'elements' => [
        ['type' => 'node', 'id' => 123, 'lat' => -6.2001, 'lon' => 106.8001, 'tags' => [
            'amenity' => 'cafe', 'name' => 'Kopi Senja', 'phone' => '+62 21 555 1234;+62 21 555 9999',
            'website' => 'kopisenja.id', 'addr:street' => 'Jalan Merdeka', 'addr:housenumber' => '12',
            'addr:city' => 'Jakarta', 'opening_hours' => 'Mo-Su 08:00-22:00', 'cuisine' => 'coffee',
        ]],
        ['type' => 'way', 'id' => 456, 'center' => ['lat' => -6.2002, 'lon' => 106.8002], 'tags' => [
            'shop' => 'bakery', 'name' => 'Roti Maju', 'website' => 'javascript:alert(1)',
        ]],
        ['type' => 'node', 'id' => 789, 'lat' => -6.2003, 'lon' => 106.8003, 'tags' => [
            'disused:amenity' => 'cafe', 'name' => 'Kafe Tutup',
        ]],
        ['type' => 'node', 'id' => 790, 'lat' => -6.2004, 'lon' => 106.8004, 'tags' => ['amenity' => 'cafe']], // no name
        ['type' => 'node', 'id' => 791, 'lat' => -6.2005, 'lon' => 106.8005, 'tags' => ['amenity' => 'bank', 'name' => 'Bank']], // other category
        ['type' => 'node', 'id' => 792, 'tags' => ['amenity' => 'cafe', 'name' => 'No coordinates']],
        ['type' => 'node', 'id' => 123, 'lat' => -6.2001, 'lon' => 106.8001, 'tags' => ['amenity' => 'cafe', 'name' => 'Duplicate']],
    ]];
}

it('maps an Overpass response to businesses with lifecycle detection', function () {
    refake([OSM_PRIMARY => Http::response(overpassSample())]);

    $result = app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories);

    expect($result->fresh)->toBeTrue()->and($result->cached)->toBeFalse()->and($result->apiCalls)->toBe(1);
    $byId = collect($result->businesses)->keyBy('placeId');
    expect($byId->keys()->all())->toBe(['node/123', 'way/456', 'node/789']);

    $cafe = $byId['node/123'];
    expect($cafe->provider)->toBe('osm')
        ->and($cafe->name)->toBe('Kopi Senja')
        ->and($cafe->categoryKey)->toBe('cafe')
        ->and($cafe->phone)->toBe('+62 21 555 1234')
        ->and($cafe->website)->toBe('https://kopisenja.id')
        ->and($cafe->address)->toBe('Jalan Merdeka No. 12, Jakarta')
        ->and($cafe->openingHours)->toBe('Mo-Su 08:00-22:00')
        ->and($cafe->isClosedPermanently)->toBeFalse()
        ->and($cafe->simulatedFields)->toBe([])
        ->and($cafe->rawTags['cuisine'])->toBe('coffee');

    expect($byId['way/456']->lat)->toBe(-6.2002)->and($byId['way/456']->website)->toBeNull();
    expect($byId['node/789']->isClosedPermanently)->toBeTrue()->and($byId['node/789']->categoryKey)->toBe('cafe');
});

it('sends the contact user agent and a per-outlet around:2000 query', function () {
    config(['hunter.overpass.contact_email' => 'ops@example.test']);
    refake([OSM_PRIMARY => Http::response(['elements' => []])]);

    app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories);

    Http::assertSent(function (Request $r) {
        return str_contains($r->url(), 'overpass-api.de')
            && $r->header('User-Agent')[0] === 'pos-merchant-hunter/1.0 (ops@example.test)'
            && str_contains($r['data'], '(around:2000,-6.20000,106.80000)')
            && str_contains($r['data'], 'out center tags;')
            && str_contains($r['data'], '"amenity"~"^(cafe)$"')
            && str_contains($r['data'], '"disused:shop"~"^(bakery)$"')
            && str_contains($r['data'], '"was:amenity"')
            && str_contains($r['data'], '"abandoned:amenity"')
            && str_contains($r['data'], '"removed:shop"')
            && str_contains($r['data'], '"demolished:shop"');
    });
});

it('returns an empty fresh result when nothing is found', function () {
    refake([OSM_PRIMARY => Http::response(['elements' => []])]);
    $result = app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories);
    expect($result->businesses)->toBe([])->and($result->fresh)->toBeTrue();
});

it('stores the response gzip+base64 in api_cache keyed by sha256 with 7 day ttl', function () {
    refake([OSM_PRIMARY => Http::response(overpassSample())]);
    app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories);

    $row = ApiCache::query()->first();
    expect(ApiCache::count())->toBe(1)
        ->and(strlen($row->cache_key))->toBe(64)
        ->and($row->provider)->toBe('osm')
        ->and($row->expires_at->toDateTimeString())->toBe('2026-10-31 01:00:00');
    $decoded = json_decode(gzdecode(base64_decode($row->payload)), true);
    expect($decoded['elements'])->toHaveCount(7);
    expect(DataSource::where('key', 'osm')->value('calls_this_month'))->toBe(1);
});

it('serves a recent cache entry without any request, flagged not fresh', function () {
    refake([OSM_PRIMARY => Http::response(overpassSample())]);
    app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories);

    refake(fn () => throw new RuntimeException('no request expected'));
    $result = app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories);

    expect($result->cached)->toBeTrue()->and($result->fresh)->toBeFalse()->and($result->apiCalls)->toBe(0)
        ->and($result->businesses)->toHaveCount(3);
});

it('bypasses the cache with ignoreCache (hunter:sync --fresh)', function () {
    refake([OSM_PRIMARY => Http::response(overpassSample())]);
    app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories);

    $result = app(OsmProvider::class)->ignoreCache()->searchNearby($this->center, 2000, $this->categories);
    expect($result->cached)->toBeFalse()->and($result->fresh)->toBeTrue();
    Http::assertSentCount(2);
});

it('retries a 429 on the mirror and succeeds', function () {
    refake([OSM_PRIMARY => Http::response('Too Many Requests', 429), OSM_MIRROR => Http::response(overpassSample())]);

    $result = app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories);

    expect($result->fresh)->toBeTrue()->and($result->businesses)->toHaveCount(3)->and($result->apiCalls)->toBe(2);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'overpass.private.coffee'));
    $backoffs = array_values(array_filter($this->sleeper->sleeps, fn ($s) => $s >= 30));
    expect($backoffs)->toHaveCount(1)->and($backoffs[0])->toBeGreaterThanOrEqual(30)->toBeLessThanOrEqual(33.0);
});

it('retries 3 times with 30/60/120 s backoff plus jitter then throws', function () {
    refake([OSM_PRIMARY => Http::response('', 504), OSM_MIRROR => Http::response('', 504)]);

    expect(fn () => app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories))
        ->toThrow(ProviderException::class, '504');

    Http::assertSentCount(4);
    $backoffs = array_values(array_filter($this->sleeper->sleeps, fn ($s) => $s >= 30));
    expect($backoffs)->toHaveCount(3);
    foreach ([30, 60, 120] as $i => $base) {
        expect($backoffs[$i])->toBeGreaterThanOrEqual($base)->toBeLessThanOrEqual($base * 1.1 + 0.01);
    }
});

it('falls back to stale cache on 429/504 and flags it not fresh', function () {
    refake([OSM_PRIMARY => Http::response(overpassSample())]);
    app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories);

    Carbon::setTestNow(Carbon::now()->addDays(2)); // beyond the recent window, within the 7 day ttl
    refake([OSM_PRIMARY => Http::response('', 429), OSM_MIRROR => Http::response('', 504)]);

    $result = app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories);
    expect($result->cached)->toBeTrue()->and($result->fresh)->toBeFalse()->and($result->businesses)->toHaveCount(3);
});

it('does not use an expired cache entry', function () {
    refake([OSM_PRIMARY => Http::response(overpassSample())]);
    app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories);

    Carbon::setTestNow(Carbon::now()->addDays(8));
    refake([OSM_PRIMARY => Http::response('', 504), OSM_MIRROR => Http::response('', 504)]);
    expect(fn () => app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories))
        ->toThrow(ProviderException::class);
});

it('treats connection timeouts as retryable failures', function () {
    refake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));
    expect(fn () => app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories))
        ->toThrow(ProviderException::class, 'connection failed');
    expect($this->sleeper->sleeps)->toContain(...array_values(array_filter($this->sleeper->sleeps, fn ($s) => $s >= 30)));
});

it('treats invalid JSON and runtime-error remarks as retryable failures', function () {
    refake([OSM_PRIMARY => Http::response('<html>busy</html>', 200), OSM_MIRROR => Http::response(['elements' => [], 'remark' => 'runtime error: Query timed out'], 200)]);
    expect(fn () => app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories))
        ->toThrow(ProviderException::class);
    Http::assertSentCount(4);
});

it('does not retry a non-retryable client error', function () {
    refake([OSM_PRIMARY => Http::response('bad query', 400)]);
    expect(fn () => app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories))
        ->toThrow(ProviderException::class, '400');
    Http::assertSentCount(1);
});

it('spaces consecutive requests by delay_seconds', function () {
    config(['hunter.overpass.delay_seconds' => 5]);
    refake([OSM_PRIMARY => Http::response(['elements' => []])]);
    app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories);
    app(OsmProvider::class)->ignoreCache()->searchNearby($this->center, 2000, $this->categories);

    expect($this->sleeper->sleeps)->toHaveCount(1)->and($this->sleeper->sleeps[0])->toBeGreaterThan(4.9)->toBeLessThanOrEqual(5.0);
});

it('opens the circuit breaker after 5 consecutive failed searches and closes it later', function () {
    refake(['*' => Http::response('', 504)]);
    for ($i = 0; $i < 5; $i++) {
        try {
            app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories);
        } catch (ProviderException) {
        }
    }
    $sent = count(Http::recorded());
    expect(app(OverpassClient::class)->circuitOpen())->toBeTrue();

    expect(fn () => app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories))
        ->toThrow(ProviderException::class, 'circuit breaker');
    expect(count(Http::recorded()))->toBe($sent);

    Carbon::setTestNow(Carbon::now()->addMinutes(16));
    refake(['*' => Http::response(['elements' => []])]);
    expect(app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories)->fresh)->toBeTrue();
    expect(app(OverpassClient::class)->circuitOpen())->toBeFalse();
});

it('a success resets the consecutive failure counter', function () {
    refake(['*' => Http::response('', 504)]);
    for ($i = 0; $i < 4; $i++) {
        try {
            app(OsmProvider::class)->searchNearby($this->center, 2000, $this->categories);
        } catch (ProviderException) {
        }
    }
    refake(['*' => Http::response(['elements' => []])]);
    app(OsmProvider::class)->ignoreCache()->searchNearby($this->center, 2000, $this->categories);
    refake(['*' => Http::response('', 504)]);
    try {
        app(OsmProvider::class)->ignoreCache()->searchNearby($this->center, 2000, $this->categories);
    } catch (ProviderException) {
    }
    expect(app(OverpassClient::class)->circuitOpen())->toBeFalse();
});

it('is available only when the osm data source is active', function () {
    expect(app(OsmProvider::class)->isAvailable())->toBeTrue();
    DataSource::where('key', 'osm')->update(['is_active' => false]);
    expect(app(OsmProvider::class)->isAvailable())->toBeFalse();
});

it('tests the connection through /api/status', function () {
    refake(['overpass-api.de/api/status' => Http::response("Connected as: 1\nCurrent time: now\n2 slots available now.")]);
    $r = app(OsmProvider::class)->testConnection();
    expect($r->ok)->toBeTrue()->and($r->message)->toContain('slots available');

    refake(['overpass-api.de/api/status' => Http::response('', 503)]);
    expect(app(OsmProvider::class)->testConnection()->ok)->toBeFalse();

    refake(fn () => throw new ConnectionException('down'));
    expect(app(OsmProvider::class)->testConnection()->ok)->toBeFalse();
});

it('reports usage from the data source', function () {
    DataSource::where('key', 'osm')->update(['calls_this_month' => 7, 'cost_search_per_1000' => 2.0]);
    $u = app(OsmProvider::class)->usage();
    expect($u->callsThisMonth)->toBe(7)->and($u->estimatedCostUsd)->toBe(0.014);
});

it('drops unsafe tag values from the query', function () {
    $c = Category::factory()->make(['osm_filters' => ['amenity' => ['cafe', 'x"]; evil', 'bar'], 'BAD KEY' => ['x']]]);
    $q = OverpassQuery::build(-6.2, 106.8, 2000, [$c]);
    expect($q)->toContain('^(cafe|bar)$')->not->toContain('evil');
});
