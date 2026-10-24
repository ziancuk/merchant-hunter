<?php

use App\Models\DataSource;
use App\Models\Outlet;
use App\Services\Providers\GoogleProvider;
use App\Services\Providers\MockProvider;
use App\Services\Providers\OsmProvider;
use App\Services\Providers\ProviderRegistry;
use Tests\Feature\Sync\FakeProvider;

beforeEach(function () {
    DataSource::create(['key' => 'osm', 'name' => 'OSM', 'is_active' => true, 'priority' => 10]);
    DataSource::create(['key' => 'google', 'name' => 'Google', 'is_active' => false, 'priority' => 5]);
    DataSource::create(['key' => 'mock', 'name' => 'Mock', 'is_active' => false, 'priority' => 90]);
    $this->outlet = new Outlet(['code' => 'X']);
    $this->keys = fn (array $providers) => array_map(fn ($p) => $p->key(), $providers);
});

it('returns only the default active source (osm) in production-like config', function () {
    config(['hunter.demo_mode' => false]);
    $r = new ProviderRegistry;
    expect(($this->keys)($r->resolveFor($this->outlet)))->toBe(['osm']);
    expect($r->make('osm'))->toBeInstanceOf(OsmProvider::class)
        ->and($r->make('google'))->toBeInstanceOf(GoogleProvider::class)
        ->and($r->make('mock'))->toBeInstanceOf(MockProvider::class)
        ->and($r->make('nope'))->toBeNull();
});

it('orders active providers by priority and skips unconfigured google', function () {
    DataSource::where('key', 'google')->update(['is_active' => true]); // active but no key
    expect(($this->keys)((new ProviderRegistry)->resolveFor($this->outlet)))->toBe(['osm']);

    DataSource::where('key', 'google')->first()->update(['api_key' => 'AIza-test']);
    expect(($this->keys)((new ProviderRegistry)->resolveFor($this->outlet)))->toBe(['google', 'osm']);
});

it('adds mock only as last-resort fallback in demo mode', function () {
    config(['hunter.demo_mode' => true]);
    expect(($this->keys)((new ProviderRegistry)->resolveFor($this->outlet)))->toBe(['osm', 'mock']);

    DataSource::where('key', 'mock')->update(['is_active' => true]);
    expect(($this->keys)((new ProviderRegistry)->resolveFor($this->outlet)))->toBe(['osm', 'mock']); // no duplicate
});

it('lets the operator force a single provider, except google without a key', function () {
    $r = new ProviderRegistry;
    expect(($this->keys)($r->resolveFor($this->outlet, 'mock')))->toBe(['mock']);
    expect(($this->keys)($r->resolveFor($this->outlet, 'osm')))->toBe(['osm']);
    expect($r->resolveFor($this->outlet, 'google'))->toBe([]);
    expect($r->resolveFor($this->outlet, 'nope'))->toBe([]);
});

it('allows replacing a provider implementation', function () {
    $fake = new FakeProvider('osm');
    $r = (new ProviderRegistry)->extend('osm', $fake);
    expect($r->make('osm'))->toBe($fake)->and($r->resolveFor($this->outlet))->toBe([$fake]);
    $fake->available = false;
    expect($r->resolveFor($this->outlet))->toBe([]);
});
