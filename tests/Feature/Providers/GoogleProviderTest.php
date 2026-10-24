<?php

use App\DTO\OutletPoint;
use App\Models\Category;
use App\Models\DataSource;
use App\Services\Providers\GoogleProvider;
use App\Services\Providers\ProviderException;
use App\Services\Providers\QuotaExceededException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->source = DataSource::create([
        'key' => 'google', 'name' => 'Google', 'is_active' => true, 'priority' => 5,
        'api_key' => 'AIza-test-key-0001', 'monthly_quota' => 100, 'cost_search_per_1000' => 32.0,
    ]);
    $this->restaurant = Category::factory()->create(['key' => 'restaurant', 'google_types' => ['restaurant']]);
    $this->cafe = Category::factory()->create(['key' => 'cafe', 'google_types' => ['cafe', 'coffee_shop']]);
    $this->noGoogle = Category::factory()->create(['key' => 'misc', 'google_types' => null]);
    $this->center = new OutletPoint(1, 'OUT-1', 'Outlet', -6.2, 106.8);
});

function googleSample(): array
{
    return ['places' => [
        ['id' => 'ChIJabc', 'displayName' => ['text' => 'Warung Enak'], 'location' => ['latitude' => -6.2001, 'longitude' => 106.8002],
            'formattedAddress' => 'Jl. Melati 3, Jakarta', 'nationalPhoneNumber' => '021 555 0101', 'websiteUri' => 'https://enak.example',
            'rating' => 4.46, 'userRatingCount' => 120, 'regularOpeningHours' => ['weekdayDescriptions' => ['Mon: 8-17', 'Tue: 8-17']]],
        ['id' => 'ChIJclosed', 'displayName' => ['text' => 'Tutup Selamanya'], 'location' => ['latitude' => -6.2003, 'longitude' => 106.8003], 'businessStatus' => 'CLOSED_PERMANENTLY'],
        ['id' => 'ChIJbroken', 'displayName' => ['text' => 'No location']],
    ]];
}

it('is available only with an api key and an active source', function () {
    $g = app(GoogleProvider::class);
    expect($g->isAvailable())->toBeTrue();
    DataSource::where('key', 'google')->update(['is_active' => false]);
    expect(app(GoogleProvider::class)->isAvailable())->toBeFalse();
    DataSource::where('key', 'google')->update(['is_active' => true, 'api_key' => null]);
    expect(app(GoogleProvider::class)->isAvailable())->toBeFalse();
    DataSource::query()->delete();
    expect(app(GoogleProvider::class)->isAvailable())->toBeFalse();
});

it('makes one call per category google_types group (max 20, no pagination) and maps places', function () {
    Http::fake(['places.googleapis.com/*' => Http::sequence()->push(googleSample())->push(['places' => [
        ['id' => 'ChIJabc', 'displayName' => ['text' => 'Dup'], 'location' => ['latitude' => -6.2, 'longitude' => 106.8]],
        ['id' => 'ChIJcafe', 'displayName' => ['text' => 'Kopi Kita'], 'location' => ['latitude' => -6.21, 'longitude' => 106.81]],
    ]])]);

    $r = app(GoogleProvider::class)->searchNearby($this->center, 2000, [$this->restaurant, $this->cafe, $this->noGoogle]);

    expect($r->apiCalls)->toBe(2)->and($r->fresh)->toBeTrue();
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $req) => $req->hasHeader('X-Goog-Api-Key', 'AIza-test-key-0001')
        && $req->hasHeader('X-Goog-FieldMask')
        && $req['maxResultCount'] === 20
        && ! isset($req['pageToken'])
        && in_array($req['includedTypes'], [['restaurant'], ['cafe', 'coffee_shop']], true));
    $byId = collect($r->businesses)->keyBy('placeId');
    expect($byId->keys()->all())->toBe(['ChIJabc', 'ChIJclosed', 'ChIJcafe']);
    $a = $byId['ChIJabc'];
    expect($a->provider)->toBe('google')->and($a->categoryKey)->toBe('restaurant')->and($a->name)->toBe('Warung Enak')
        ->and($a->rating)->toBe(4.5)->and($a->reviewCount)->toBe(120)->and($a->phone)->toBe('021 555 0101')
        ->and($a->openingHours)->toBe('Mon: 8-17; Tue: 8-17')->and($a->simulatedFields)->toBe([]);
    expect($byId['ChIJclosed']->isClosedPermanently)->toBeTrue();
    expect($this->source->fresh()->calls_this_month)->toBe(2);
});

it('refuses the whole search when it would exceed the monthly quota, without any request', function () {
    Http::fake();
    $this->source->update(['monthly_quota' => 10]);
    $this->source->forceFill(['calls_this_month' => 9])->save();

    expect(fn () => app(GoogleProvider::class)->searchNearby($this->center, 2000, [$this->restaurant, $this->cafe]))
        ->toThrow(QuotaExceededException::class);
    Http::assertNothingSent();
    expect($this->source->fresh()->calls_this_month)->toBe(9);
});

it('refuses once the quota is fully used', function () {
    Http::fake();
    $this->source->forceFill(['calls_this_month' => 100])->save();
    expect(fn () => app(GoogleProvider::class)->searchNearby($this->center, 2000, [$this->restaurant]))
        ->toThrow(QuotaExceededException::class);
    Http::assertNothingSent();
});

it('turns a Google 429 / RESOURCE_EXHAUSTED into a quota error', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota']], 429)]);
    expect(fn () => app(GoogleProvider::class)->searchNearby($this->center, 2000, [$this->restaurant]))
        ->toThrow(QuotaExceededException::class);
    expect($this->source->fresh()->calls_this_month)->toBe(1);
});

it('raises provider errors for other failures and connection problems', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 400)]);
    expect(fn () => app(GoogleProvider::class)->searchNearby($this->center, 2000, [$this->restaurant]))
        ->toThrow(ProviderException::class, 'API key not valid');

    Http::swap(new Factory);
    Http::fake(fn () => throw new ConnectionException('timeout'));
    expect(fn () => app(GoogleProvider::class)->searchNearby($this->center, 2000, [$this->restaurant]))
        ->toThrow(ProviderException::class, 'connection failed');
});

it('refuses to search when inactive', function () {
    Http::fake();
    $this->source->update(['is_active' => false]);
    expect(fn () => app(GoogleProvider::class)->searchNearby($this->center, 2000, [$this->restaurant]))
        ->toThrow(ProviderException::class, 'inactive');
    Http::assertNothingSent();
});

it('tests the connection and counts that call', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(['places' => []])]);
    $r = app(GoogleProvider::class)->testConnection();
    expect($r->ok)->toBeTrue();
    expect($this->source->fresh()->calls_this_month)->toBe(1);

    $this->source->forceFill(['calls_this_month' => 100])->save();
    expect(app(GoogleProvider::class)->testConnection()->ok)->toBeFalse();

    $this->source->update(['is_active' => false]);
    expect(app(GoogleProvider::class)->testConnection()->ok)->toBeFalse();
});

it('reports usage and estimated cost', function () {
    $this->source->forceFill(['calls_this_month' => 50])->save();
    $u = app(GoogleProvider::class)->usage();
    expect($u->callsThisMonth)->toBe(50)->and($u->monthlyQuota)->toBe(100)->and($u->estimatedCostUsd)->toBe(1.6);
});
