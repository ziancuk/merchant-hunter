<?php

use App\Models\Prospect;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    Cache::flush();
    dm_login();
});

const BBOX_JAKARTA = 'bbox=106.6,-6.4,107.0,-6.0';

it('returns outlets as a columnar payload', function () {
    $p = dm_province();
    $a = dm_outlet(['province_id' => $p->id, 'code' => 'OUT-001', 'name' => 'Toko Sinar Jaya', 'lat' => -6.2, 'lng' => 106.8]);
    dm_outlet(['code' => 'OUT-002', 'name' => 'Warung Bu Tini', 'lat' => -7.25, 'lng' => 112.7]);
    dm_outlet(['code' => 'OUT-003', 'is_active' => false, 'lat' => -1, 'lng' => 100]);
    dm_outlet(['code' => 'OUT-004', 'lat' => null, 'lng' => null]);

    $this->getJson('/api/map/outlets')->assertOk()
        ->assertJsonPath('count', 2)
        ->assertJsonPath('codes', ['OUT-001', 'OUT-002'])
        ->assertJsonPath('names.0', 'Toko Sinar Jaya')
        ->assertJsonPath('ids.0', $a->id)
        ->assertJsonStructure(['count', 'ids', 'codes', 'names', 'lng', 'lat', 'province_ids']);

    $this->getJson('/api/map/outlets?province_id='.$p->id)->assertOk()->assertJsonPath('count', 1);
    $this->getJson('/api/map/outlets?province_id=999999')->assertStatus(422);
});

it('returns columnar prospect points at zoom >= 6 with status and flag encoding', function () {
    $a = dm_prospect(['lat' => -6.2, 'lng' => 106.8, 'score' => 82, 'is_new_open' => true, 'is_simulated' => false]);
    $b = dm_prospect(['lat' => -6.25, 'lng' => 106.85, 'score' => 47, 'pipeline_status' => 'contacted', 'is_simulated' => true, 'do_not_contact' => true]);
    $c = dm_prospect(['lat' => -6.3, 'lng' => 106.9, 'score' => 10, 'pipeline_status' => 'lost', 'lifecycle_status' => 'closed']);
    dm_prospect(['lat' => 10, 'lng' => 10]); // outside bbox
    dm_prospect(['lat' => null, 'lng' => null]); // no coordinates

    $r = $this->getJson('/api/map/prospects?'.BBOX_JAKARTA.'&zoom=12&include_closed=1')->assertOk();

    $r->assertJsonPath('type', 'points')->assertJsonPath('count', 3)->assertJsonPath('truncated', false);
    expect($r->json('ids'))->toBe([$a->id, $b->id, $c->id])   // ordered by score desc
        ->and($r->json('score'))->toBe([82, 47, 10])
        ->and($r->json('status'))->toBe([0, 1, 4])
        ->and($r->json('flags'))->toBe([1, 2 | 8, 2 | 4])
        ->and($r->json('lng.0'))->toBe(106.8)
        ->and($r->json('lat.0'))->toBe(-6.2);
});

it('hides closed prospects unless include_closed is set', function () {
    dm_prospect(['lat' => -6.2, 'lng' => 106.8]);
    dm_prospect(['lat' => -6.2, 'lng' => 106.81, 'lifecycle_status' => 'closed']);

    $this->getJson('/api/map/prospects?'.BBOX_JAKARTA.'&zoom=10')->assertJsonPath('count', 1);
    $this->getJson('/api/map/prospects?'.BBOX_JAKARTA.'&zoom=10&include_closed=1')->assertJsonPath('count', 2);
});

it('applies every filter', function () {
    $cafe = dm_category(['key' => 'f_cafe']);
    $other = dm_category(['key' => 'f_other']);
    $prov = dm_province();

    $match = dm_prospect([
        'category_id' => $cafe->id, 'province_id' => $prov->id, 'city' => 'Bandung', 'score' => 80, 'rating' => 4.6,
        'phone' => '081255550101', 'pipeline_status' => 'demo', 'nearest_outlet_distance_m' => 300, 'is_new_open' => true,
        'lat' => -6.2, 'lng' => 106.8,
    ]);
    dm_prospect(['category_id' => $other->id, 'lat' => -6.2, 'lng' => 106.8, 'score' => 80]);
    dm_prospect(['category_id' => $cafe->id, 'lat' => -6.2, 'lng' => 106.8, 'score' => 20]);
    dm_prospect(['category_id' => $cafe->id, 'province_id' => $prov->id, 'lat' => -6.2, 'lng' => 106.8, 'score' => 80, 'phone' => null]);

    $base = '/api/map/prospects?'.BBOX_JAKARTA.'&zoom=10';
    $ids = fn (string $q) => $this->getJson($base.$q)->assertOk()->json('ids');

    expect($ids("&category_id[]={$cafe->id}&score_min=70&score_max=90&rating_min=4&has_phone=1&province_id={$prov->id}&city=Bandung&pipeline_status[]=demo&max_distance_m=500&new_open_only=1"))
        ->toBe([$match->id])
        ->and($ids('&category_id[]='.$other->id))->toHaveCount(1)
        ->and($ids('&has_phone=0'))->toHaveCount(3)
        ->and($ids('&score_max=30'))->toHaveCount(1)
        ->and($ids('&pipeline_status[]=won'))->toBe([])
        ->and($ids('&max_distance_m=100'))->toBe([])
        ->and($ids('&city=Jakarta'))->toBe([]);
});

it('counts simulated phones as phones for the has_phone filter', function () {
    dm_prospect(['lat' => -6.2, 'lng' => 106.8, 'phone' => '0999-1234-5678', 'phone_simulated' => true]);

    $this->getJson('/api/map/prospects?'.BBOX_JAKARTA.'&zoom=10&has_phone=1')->assertJsonPath('count', 1);
});

it('aggregates a grid below zoom 6', function () {
    foreach ([[-6.2, 106.8, 80], [-6.21, 106.81, 60], [-6.22, 106.82, 90]] as [$lat, $lng, $score]) {
        dm_prospect(['lat' => $lat, 'lng' => $lng, 'score' => $score]);
    }
    dm_prospect(['lat' => 3.5, 'lng' => 98.7, 'score' => 40]);

    $r = $this->getJson('/api/map/prospects?bbox=90,-10,120,10&zoom=4')->assertOk();

    $r->assertJsonPath('type', 'grid')->assertJsonPath('cell_size_deg', 1)
        ->assertJsonStructure(['cells' => [['lng', 'lat', 'count', 'avg_score', 'hot']]]);
    $cells = collect($r->json('cells'))->keyBy('count');
    expect($cells->has(3))->toBeTrue()
        ->and($cells[3]['avg_score'])->toBe(76.7)
        ->and($cells[3]['hot'])->toBe(2)
        ->and($r->json('cells'))->toHaveCount(2);
});

it('truncates at 20,000 points and says so', function () {
    $category = dm_category();
    $now = now()->toDateTimeString();
    $rows = [];
    for ($i = 0; $i < 20005; $i++) {
        $rows[] = [
            'provider' => 'mock', 'place_id' => 'bulk/'.$i, 'name' => 'B'.$i, 'category_id' => $category->id,
            'lat' => -6.2 + ($i % 100) / 10000, 'lng' => 106.8 + intdiv($i, 100) / 10000, 'score' => $i % 100,
            'first_seen_at' => $now, 'last_seen_at' => $now, 'fetched_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ];
    }
    foreach (array_chunk($rows, 1000) as $chunk) {
        DB::table('prospects')->insert($chunk);
    }

    $r = $this->getJson('/api/map/prospects?'.BBOX_JAKARTA.'&zoom=14')->assertOk();

    expect($r->json('truncated'))->toBeTrue()
        ->and($r->json('count'))->toBe(20000)
        ->and($r->json('ids'))->toHaveCount(20000)
        ->and($r->json('score.0'))->toBe(99); // best scores are kept
});

it('uses at most 8 queries and no N+1 for ~500 prospects', function () {
    $category = dm_category();
    $now = now()->toDateTimeString();
    $rows = [];
    for ($i = 0; $i < 500; $i++) {
        $rows[] = [
            'provider' => 'mock', 'place_id' => 'q/'.$i, 'name' => 'Q'.$i, 'category_id' => $category->id,
            'lat' => -6.2 + $i / 100000, 'lng' => 106.8, 'score' => 50, 'first_seen_at' => $now, 'last_seen_at' => $now,
            'fetched_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ];
    }
    DB::table('prospects')->insert($rows);

    DB::enableQueryLog();
    $this->getJson('/api/map/prospects?'.BBOX_JAKARTA.'&zoom=12')->assertOk()->assertJsonPath('count', 500);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(8)->and($queries)->toBeLessThanOrEqual(2);
});

it('sends an ETag and a 30 s private cache header and honours If-None-Match', function () {
    dm_prospect(['lat' => -6.2, 'lng' => 106.8]);
    $url = '/api/map/prospects?'.BBOX_JAKARTA.'&zoom=12';

    $first = $this->getJson($url)->assertOk();
    $etag = $first->headers->get('ETag');

    expect($etag)->not->toBeNull()
        ->and($first->headers->get('Cache-Control'))->toContain('private')->toContain('max-age=30');

    $this->getJson($url, ['If-None-Match' => $etag])->assertStatus(304);

    dm_prospect(['lat' => -6.21, 'lng' => 106.8]);
    $this->getJson($url, ['If-None-Match' => $etag])->assertOk()->assertJsonPath('count', 2);
});

it('validates map query parameters with the JSON error format', function () {
    $this->getJson('/api/map/prospects')->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['bbox', 'zoom']]);
    $this->getJson('/api/map/prospects?bbox=1,2,3&zoom=5')->assertStatus(422)->assertJsonValidationErrors('bbox');
    $this->getJson('/api/map/prospects?bbox=106,-7,107,-6&zoom=30')->assertStatus(422)->assertJsonValidationErrors('zoom');
    $this->getJson('/api/map/prospects?bbox=106,-7,107,-6&zoom=5&pipeline_status[]=bogus')->assertStatus(422)->assertJsonValidationErrors('pipeline_status.0');
    $this->getJson('/api/map/prospects?bbox=106,-7,107,-6&zoom=5&score_min=500')->assertStatus(422);
});

it('finds white space: dense cells with no outlet within 500 m', function () {
    dm_outlet(['lat' => -6.5, 'lng' => 107.2]);
    for ($i = 0; $i < 16; $i++) {
        dm_prospect(['lat' => -6.2051 + $i / 100000, 'lng' => 106.8051, 'score' => 60]);
    }
    // 14 in another cell: below the threshold
    for ($i = 0; $i < 14; $i++) {
        dm_prospect(['lat' => -6.3051 + $i / 100000, 'lng' => 106.8051]);
    }

    $r = $this->getJson('/api/map/whitespace?'.BBOX_JAKARTA)->assertOk();

    $r->assertJsonPath('cell_size_m', 1000)->assertJsonCount(1, 'cells')
        ->assertJsonPath('cells.0.prospects', 16)
        ->assertJsonPath('cells.0.avg_score', 60)
        ->assertJsonPath('cells.0.id', '-6.21:106.80')
        ->assertJsonStructure(['cells' => [['id', 'lng', 'lat', 'prospects', 'avg_score', 'nearest_outlet_m']]]);
    expect($r->json('cells.0.nearest_outlet_m'))->toBeGreaterThan(500);
    expect($r->json('cells.0'))->not->toHaveKey('province_id');
});

it('excludes dense cells that have an outlet within 500 m', function () {
    dm_outlet(['lat' => -6.2051, 'lng' => 106.8071]); // ~220 m from the cell centre
    for ($i = 0; $i < 20; $i++) {
        dm_prospect(['lat' => -6.2051 + $i / 100000, 'lng' => 106.8051]);
    }

    $this->getJson('/api/map/whitespace?'.BBOX_JAKARTA)->assertOk()->assertJsonCount(0, 'cells');
});

it('ignores closed prospects and honours the category filter in white space', function () {
    $keep = dm_category(['key' => 'ws_keep']);
    for ($i = 0; $i < 16; $i++) {
        dm_prospect(['category_id' => $keep->id, 'lat' => -6.2051, 'lng' => 106.8051, 'lifecycle_status' => $i < 2 ? 'closed' : 'open']);
    }

    $this->getJson('/api/map/whitespace?'.BBOX_JAKARTA)->assertJsonCount(0, 'cells'); // only 14 open
    $other = dm_category(['key' => 'ws_other']);
    $this->getJson('/api/map/whitespace?'.BBOX_JAKARTA.'&category_id[]='.$other->id)->assertJsonCount(0, 'cells');
});

it('caches white space until hunter.whitespace.version changes', function () {
    for ($i = 0; $i < 16; $i++) {
        dm_prospect(['lat' => -6.2051, 'lng' => 106.8051]);
    }
    $url = '/api/map/whitespace?'.BBOX_JAKARTA;

    $this->getJson($url)->assertJsonPath('cells.0.prospects', 16);
    dm_prospect(['lat' => -6.2051, 'lng' => 106.8051]);

    $this->getJson($url)->assertJsonPath('cells.0.prospects', 16); // still cached

    Cache::put('hunter.whitespace.version', 2);
    $this->getJson($url)->assertJsonPath('cells.0.prospects', 17);
});

it('searches outlets, prospects and provinces with a minimum of two characters', function () {
    dm_province(['name' => 'Jawa Kopi', 'capital_city' => 'X']);
    dm_outlet(['name' => 'Toko Kopi Jaya', 'lat' => -6.2, 'lng' => 106.8]);
    dm_prospect(['name' => 'Kopi Senja', 'city' => 'Jakarta', 'lat' => -6.2, 'lng' => 106.8, 'score' => 71]);
    dm_prospect(['name' => 'Tutup Kopi', 'lat' => -6.2, 'lng' => 106.8, 'lifecycle_status' => 'closed']);
    dm_prospect(['name' => 'Bengkel Maju', 'lat' => -6.2, 'lng' => 106.8]);

    $r = $this->getJson('/api/search?q=kopi')->assertOk();

    $r->assertJsonCount(1, 'outlets')->assertJsonCount(1, 'prospects')->assertJsonCount(1, 'provinces')
        ->assertJsonPath('prospects.0.name', 'Kopi Senja')
        ->assertJsonPath('prospects.0.score', 71)
        ->assertJsonStructure([
            'outlets' => [['id', 'name', 'city', 'lng', 'lat']],
            'prospects' => [['id', 'name', 'category', 'city', 'score', 'lng', 'lat']],
            'provinces' => [['id', 'name', 'lng', 'lat']],
        ]);

    $this->getJson('/api/search?q=k')->assertStatus(422)->assertJsonValidationErrors('q');
    $this->getJson('/api/search')->assertStatus(422);
});

it('caps each search group at 8 and escapes LIKE wildcards', function () {
    for ($i = 0; $i < 12; $i++) {
        dm_prospect(['name' => 'Warung '.$i, 'lat' => -6.2, 'lng' => 106.8]);
    }
    dm_prospect(['name' => '100% Halal', 'lat' => -6.2, 'lng' => 106.8]);

    $this->getJson('/api/search?q=warung')->assertJsonCount(8, 'prospects');
    $this->getJson('/api/search?q=0%25')->assertJsonCount(1, 'prospects'); // a literal %
    $this->getJson('/api/search?q=__')->assertJsonCount(0, 'prospects');  // _ is not a wildcard
});

it('keeps the raw business name untouched in JSON (XSS payload is data, not markup)', function () {
    $payload = '<script>alert(1)</script>';
    dm_prospect(['name' => $payload, 'lat' => -6.2, 'lng' => 106.8]);

    $r = $this->getJson('/api/search?q=script')->assertOk();

    expect($r->json('prospects.0.name'))->toBe($payload)
        ->and($r->headers->get('Content-Type'))->toContain('application/json');
});

it('never reads prospects without a coordinate into the map', function () {
    dm_prospect(['lat' => null, 'lng' => null]);

    $this->getJson('/api/map/prospects?'.BBOX_JAKARTA.'&zoom=12')->assertJsonPath('count', 0);
    expect(Prospect::count())->toBe(1);
});
