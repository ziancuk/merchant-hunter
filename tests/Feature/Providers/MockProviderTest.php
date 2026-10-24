<?php

use App\DTO\BusinessDTO;
use App\DTO\OutletPoint;
use App\Models\Category;
use App\Services\Geo\Haversine;
use App\Services\Providers\MockProvider;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->categories = [
        Category::factory()->create(['key' => 'restaurant', 'weight' => 85]),
        Category::factory()->create(['key' => 'cafe', 'weight' => 80]),
        Category::factory()->create(['key' => 'laundry', 'weight' => 20]),
    ];
});

function mockPoint(string $code, float $lat = -6.2, float $lng = 106.8): OutletPoint
{
    return new OutletPoint(1, $code, 'Outlet '.$code, $lat, $lng);
}

it('is deterministic for the same outlet and seed, and differs otherwise', function () {
    Http::preventStrayRequests();
    $a1 = (new MockProvider(42))->searchNearby(mockPoint('OUT-1'), 2000, $this->categories);
    $a2 = (new MockProvider(42))->searchNearby(mockPoint('OUT-1'), 2000, $this->categories);
    $b = (new MockProvider(42))->searchNearby(mockPoint('OUT-2'), 2000, $this->categories);
    $c = (new MockProvider(43))->searchNearby(mockPoint('OUT-1'), 2000, $this->categories);

    $strip = fn ($r) => array_map(fn (BusinessDTO $x) => [$x->placeId, $x->name, $x->lat, $x->lng, $x->phone, $x->rating, $x->reviewCount, $x->categoryKey, $x->address], $r->businesses);
    expect($strip($a1))->toBe($strip($a2));
    expect($strip($a1))->not->toBe($strip($b));
    expect($strip($a1))->not->toBe($strip($c));
    expect($a1->apiCalls)->toBe(0)->and($a1->fresh)->toBeTrue()->and($a1->cached)->toBeFalse();
});

it('produces 55 to 95 businesses per outlet within 2 km with unique place ids', function () {
    $counts = [];
    foreach (range(1, 25) as $i) {
        $r = (new MockProvider(42))->searchNearby(mockPoint("OUT-$i"), 2000, $this->categories);
        $counts[] = count($r->businesses);
        $ids = array_map(fn ($b) => $b->placeId, $r->businesses);
        expect(count(array_unique($ids)))->toBe(count($ids));
        foreach ($r->businesses as $b) {
            expect(Haversine::distance(-6.2, 106.8, $b->lat, $b->lng))->toBeLessThanOrEqual(2000.0);
        }
    }
    expect(min($counts))->toBeGreaterThanOrEqual(55)->and(max($counts))->toBeLessThanOrEqual(95);
    expect(count(array_unique($counts)))->toBeGreaterThan(5);
    expect(array_sum($counts) / 25)->toBeGreaterThan(65)->toBeLessThan(85);
});

it('fills simulated fields with non-routable phones and flags them', function () {
    $r = (new MockProvider(42))->searchNearby(mockPoint('OUT-9'), 2000, $this->categories);
    $withPhone = 0;
    foreach ($r->businesses as $b) {
        expect($b->provider)->toBe('mock')->and($b->name)->not->toBe('')->and($b->isClosedPermanently)->toBeFalse();
        if ($b->phone !== null) {
            $withPhone++;
            expect($b->phone)->toMatch('/^0999-\d{4}-\d{4}$/')->and($b->isSimulated('phone'))->toBeTrue();
        } else {
            expect($b->isSimulated('phone'))->toBeFalse();
        }
        if ($b->rating !== null) {
            expect($b->rating)->toBeGreaterThanOrEqual(3.0)->toBeLessThanOrEqual(5.0)
                ->and($b->reviewCount)->toBeInt()->and($b->isSimulated('rating'))->toBeTrue()
                ->and($b->isSimulated('review_count'))->toBeTrue();
        }
    }
    expect($withPhone)->toBeGreaterThan(0);
});

it('picks categories by weight', function () {
    $tally = ['restaurant' => 0, 'cafe' => 0, 'laundry' => 0];
    foreach (range(1, 20) as $i) {
        foreach ((new MockProvider(42))->searchNearby(mockPoint("W-$i"), 2000, $this->categories)->businesses as $b) {
            $tally[$b->categoryKey]++;
        }
    }
    expect($tally['restaurant'])->toBeGreaterThan($tally['laundry'])->and($tally['laundry'])->toBeGreaterThan(0);
});

it('returns nothing without categories and is always available', function () {
    $m = new MockProvider;
    expect($m->searchNearby(mockPoint('X'), 2000, [])->businesses)->toBe([]);
    expect($m->isAvailable())->toBeTrue()->and($m->key())->toBe('mock')
        ->and($m->testConnection()->ok)->toBeTrue()->and($m->usage()->callsThisMonth)->toBe(0);
    expect($m->withSeed(7))->toBeInstanceOf(MockProvider::class);
});

it('enriches only empty fields deterministically and marks them simulated', function () {
    $m = new MockProvider(42);
    $real = new BusinessDTO('osm', 'node/1', 'Kopi', 'cafe', -6.2, 106.8, phone: '+62211234', rating: null, reviewCount: null);
    $e1 = $m->enrich($real);
    $e2 = $m->enrich($real);

    expect($e1->phone)->toBe('+62211234')
        ->and($e1->rating)->not->toBeNull()
        ->and($e1->reviewCount)->not->toBeNull()
        ->and($e1->simulatedFields)->toBe(['rating', 'review_count'])
        ->and($e1->rating)->toBe($e2->rating);

    $bare = $m->enrich(new BusinessDTO('osm', 'node/2', 'Roti', 'bakery', -6.2, 106.8));
    expect($bare->phone)->toMatch('/^0999-/')->and($bare->simulatedFields)->toBe(['phone', 'rating', 'review_count']);
});
