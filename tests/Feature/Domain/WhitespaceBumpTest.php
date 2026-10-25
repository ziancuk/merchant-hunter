<?php

use App\Services\Sync\WhitespaceCache;
use Illuminate\Support\Facades\Cache;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    Cache::flush();
    dm_login();
});

it('bumps atomically from a single version key', function () {
    expect(WhitespaceCache::version())->toBe(1);

    $a = WhitespaceCache::bump();
    $b = WhitespaceCache::bump();

    expect([$a, $b])->toBe([2, 3])->and(Cache::get(WhitespaceCache::VERSION_KEY))->toBe(3);
});

it('bumps when an outlet is deactivated through DELETE or PUT is_active', function () {
    $outlet = dm_outlet(['is_active' => true]);
    $before = WhitespaceCache::version();

    $this->deleteJson("/api/master/outlets/{$outlet->id}")->assertOk()->assertJsonPath('is_active', false);
    expect(WhitespaceCache::version())->toBe($before + 1);

    // Deleting an already inactive outlet changes nothing and must not churn the cache.
    $this->deleteJson("/api/master/outlets/{$outlet->id}")->assertOk();
    expect(WhitespaceCache::version())->toBe($before + 1);

    $this->putJson("/api/master/outlets/{$outlet->id}", [
        'code' => $outlet->code, 'name' => $outlet->name, 'city' => $outlet->city, 'province_id' => $outlet->province_id, 'is_active' => true,
    ])->assertOk();
    expect(WhitespaceCache::version())->toBe($before + 2);
});

it('does not bump on a plain rename without coordinates or activation change', function () {
    $outlet = dm_outlet(['is_active' => true, 'lat' => -6.2, 'lng' => 106.8]);
    $before = WhitespaceCache::version();

    $this->putJson("/api/master/outlets/{$outlet->id}", [
        'code' => $outlet->code, 'name' => 'Renamed', 'city' => $outlet->city, 'province_id' => $outlet->province_id,
    ])->assertOk();

    expect(WhitespaceCache::version())->toBe($before);
});
