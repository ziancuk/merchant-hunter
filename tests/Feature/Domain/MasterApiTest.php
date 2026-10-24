<?php

use App\DTO\ConnectionStatus;
use App\Jobs\GeocodeOutletJob;
use App\Models\Category;
use App\Models\DataSource;
use App\Models\Outlet;
use App\Models\Province;
use App\Models\SchedulerRun;
use App\Models\SchedulerRunOutlet;
use App\Services\Master\ConnectionTester;
use App\Services\Master\ProspectReconciler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    Cache::flush();
    dm_login();
});

function dm_source(string $key = 'google', array $a = []): DataSource
{
    return DataSource::create($a + ['key' => $key, 'name' => ucfirst($key), 'is_active' => false, 'priority' => 5]);
}

function dm_fake_reconciler(): object
{
    $fake = new class implements ProspectReconciler
    {
        public array $calls = [];

        public function reconcile(array $outletIds): int
        {
            $this->calls[] = $outletIds;

            return 0;
        }
    };
    app()->instance(ProspectReconciler::class, $fake);

    return $fake;
}

// ---------------------------------------------------------------- data sources

it('lists sources without ever exposing the key', function () {
    $s = dm_source('google');
    $s->api_key = 'AIza-SECRET-KEY-a1b2';
    $s->save();
    dm_source('osm', ['is_active' => true, 'priority' => 10]);

    $r = $this->getJson('/api/master/data-sources')->assertOk();

    $r->assertJsonStructure(['sources' => [['id', 'key', 'name', 'is_active', 'priority', 'has_key', 'key_hint', 'monthly_quota', 'calls_this_month',
        'cost_search_per_1000', 'cost_details_per_1000', 'last_run_at', 'last_status', 'last_error', 'config']]])
        ->assertJsonPath('sources.0.key', 'google')->assertJsonPath('sources.0.has_key', true)->assertJsonPath('sources.0.key_hint', '…a1b2')
        ->assertJsonPath('sources.1.has_key', false)->assertJsonPath('sources.1.key_hint', null);
    expect($r->getContent())->not->toContain('SECRET')->not->toContain('api_key');
});

it('stores the key encrypted and never echoes it back', function () {
    $s = dm_source('google');

    $r = $this->putJson("/api/master/data-sources/{$s->id}", ['api_key' => 'AIzaSyVERYSECRET9876', 'monthly_quota' => 1000])->assertOk();

    $r->assertJsonPath('has_key', true)->assertJsonPath('key_hint', '…9876')->assertJsonPath('monthly_quota', 1000);
    expect($r->getContent())->not->toContain('VERYSECRET')
        ->and(DB::table('data_sources')->where('id', $s->id)->value('api_key'))->not->toContain('VERYSECRET')
        ->and($s->fresh()->api_key)->toBe('AIzaSyVERYSECRET9876');
    expect($this->getJson('/api/master/data-sources')->getContent())->not->toContain('VERYSECRET');
    expect($this->get('/master/data-sources')->getContent())->not->toContain('VERYSECRET');
});

it('keeps the existing key when api_key is omitted and clears it with clear_key', function () {
    $s = dm_source('google', ['api_key' => 'KEYKEYKEY1234']);

    $this->putJson("/api/master/data-sources/{$s->id}", ['priority' => 3])->assertOk()->assertJsonPath('has_key', true);
    expect($s->fresh()->api_key)->toBe('KEYKEYKEY1234');

    $this->putJson("/api/master/data-sources/{$s->id}", ['clear_key' => true])->assertOk()->assertJsonPath('has_key', false);
    expect($s->fresh()->api_key)->toBeNull();
});

it('refuses to activate Google without a key but activates it with one', function () {
    $s = dm_source('google');

    $this->putJson("/api/master/data-sources/{$s->id}", ['is_active' => true])->assertStatus(422)->assertJsonValidationErrors('is_active');
    expect($s->fresh()->is_active)->toBeFalse();

    $this->putJson("/api/master/data-sources/{$s->id}", ['is_active' => true, 'api_key' => 'abcd1234efgh'])->assertOk()->assertJsonPath('is_active', true);

    $this->putJson("/api/master/data-sources/{$s->id}", ['clear_key' => true])->assertStatus(422); // would leave an active google without key
    expect($s->fresh()->api_key)->toBe('abcd1234efgh');
});

it('activates sources that need no key', function () {
    $osm = dm_source('osm');

    $this->putJson("/api/master/data-sources/{$osm->id}", ['is_active' => true, 'priority' => 1])->assertOk()->assertJsonPath('is_active', true)->assertJsonPath('priority', 1);
});

it('ignores identity and counter fields in data source updates and validates input', function () {
    $s = dm_source('osm');

    $this->putJson("/api/master/data-sources/{$s->id}", ['key' => 'hacked', 'name' => 'Hacked', 'calls_this_month' => 99999])->assertOk();
    $s->refresh();
    expect($s->key)->toBe('osm')->and($s->name)->toBe('Osm')->and($s->calls_this_month)->toBe(0);

    $this->putJson("/api/master/data-sources/{$s->id}", ['priority' => 'high'])->assertStatus(422)->assertJsonValidationErrors('priority');
    $this->putJson("/api/master/data-sources/{$s->id}", ['monthly_quota' => -1])->assertStatus(422);
    $this->putJson('/api/master/data-sources/9999', ['priority' => 1])->assertNotFound();
});

it('tests a connection through the ConnectionTester seam', function () {
    $s = dm_source('osm');
    app()->instance(ConnectionTester::class, new class implements ConnectionTester
    {
        public function test(DataSource $source): ConnectionStatus
        {
            return new ConnectionStatus(true, 'OK dari '.$source->key, 42);
        }
    });

    $this->postJson("/api/master/data-sources/{$s->id}/test")->assertOk()->assertExactJson(['ok' => true, 'message' => 'OK dari osm', 'latency_ms' => 42]);
});

it('reports a failed connection test without leaking exception text', function () {
    $s = dm_source('google', ['api_key' => 'LEAKYKEY1234']);
    app()->instance(ConnectionTester::class, new class implements ConnectionTester
    {
        public function test(DataSource $source): ConnectionStatus
        {
            throw new RuntimeException('GET https://x/?key=LEAKYKEY1234 failed');
        }
    });

    $r = $this->postJson("/api/master/data-sources/{$s->id}/test")->assertOk()->assertJsonPath('ok', false);
    expect($r->getContent())->not->toContain('LEAKYKEY');
});

it('falls back to an "unconfigured" tester until the real one is bound', function () {
    $s = dm_source('osm');

    $this->postJson("/api/master/data-sources/{$s->id}/test")->assertOk()->assertJsonPath('ok', false)->assertJsonStructure(['ok', 'message', 'latency_ms']);
});

it('rate-limits connection tests', function () {
    $s = dm_source('osm');

    foreach (range(1, 6) as $i) {
        $this->postJson("/api/master/data-sources/{$s->id}/test")->assertOk();
    }
    $this->postJson("/api/master/data-sources/{$s->id}/test")->assertStatus(429);
});

// ---------------------------------------------------------------- outlets

it('lists outlets with pagination meta, search and filters', function () {
    foreach (range(1, 30) as $i) {
        dm_outlet(['code' => sprintf('OUT-%03d', $i), 'name' => 'Toko '.$i]);
    }

    $r = $this->getJson('/api/master/outlets')->assertOk();
    expect($r->json('data'))->toHaveCount(25)->and($r->json('meta'))->toBe(['current_page' => 1, 'last_page' => 2, 'total' => 30, 'per_page' => 25]);
    expect($r->json('data.0'))->toHaveKeys(['id', 'code', 'name', 'address', 'city', 'province_id', 'lat', 'lng', 'geocode_status', 'is_active']);

    $this->getJson('/api/master/outlets?page=2')->assertJsonCount(5, 'data');
    $this->getJson('/api/master/outlets?search=OUT-007')->assertJsonCount(1, 'data');
    $this->getJson('/api/master/outlets?geocode_status=bogus')->assertStatus(422);
});

it('creates an outlet with coordinates as manual and reconciles prospects', function () {
    $fake = dm_fake_reconciler();
    Queue::fake();
    $prov = dm_province();

    $r = $this->postJson('/api/master/outlets', ['code' => 'OUT-900', 'name' => 'Toko Baru', 'city' => 'Bandung', 'province_id' => $prov->id, 'lat' => -6.9, 'lng' => 107.6])->assertCreated();

    $r->assertJsonPath('code', 'OUT-900')->assertJsonPath('geocode_status', 'manual')->assertJsonPath('is_simulated', false);
    expect($fake->calls)->toHaveCount(1);
    Queue::assertNothingPushed();
});

it('queues geocoding when an outlet is created without coordinates', function () {
    Queue::fake();
    $prov = dm_province();

    $this->postJson('/api/master/outlets', ['code' => 'OUT-901', 'name' => 'Tanpa Koordinat', 'city' => 'Bandung', 'province_id' => $prov->id])
        ->assertCreated()->assertJsonPath('lat', null);

    Queue::assertPushed(GeocodeOutletJob::class, 1);
});

it('validates outlet input', function () {
    $prov = dm_province();
    dm_outlet(['code' => 'OUT-DUP']);

    $this->postJson('/api/master/outlets', [])->assertStatus(422)->assertJsonValidationErrors(['code', 'name', 'city', 'province_id']);
    $this->postJson('/api/master/outlets', ['code' => 'OUT-DUP', 'name' => 'x', 'city' => 'x', 'province_id' => $prov->id])->assertStatus(422)->assertJsonValidationErrors('code');
    $this->postJson('/api/master/outlets', ['code' => 'N', 'name' => 'x', 'city' => 'x', 'province_id' => $prov->id, 'lat' => 95, 'lng' => 0])->assertStatus(422)->assertJsonValidationErrors('lat');
    $this->postJson('/api/master/outlets', ['code' => 'N', 'name' => 'x', 'city' => 'x', 'province_id' => $prov->id, 'lat' => 10])->assertStatus(422)->assertJsonValidationErrors('lng');
});

it('updates an outlet, keeping its coordinates when none are sent', function () {
    Queue::fake();
    $o = dm_outlet(['code' => 'OUT-050', 'name' => 'Lama', 'lat' => -6.2, 'lng' => 106.8]);

    $this->putJson("/api/master/outlets/{$o->id}", ['code' => 'OUT-050', 'name' => 'Baru', 'city' => 'Jakarta', 'province_id' => $o->province_id])->assertOk()->assertJsonPath('name', 'Baru');

    expect($o->fresh()->lat)->toBe(-6.2)->and($o->fresh()->name)->toBe('Baru');
    Queue::assertNothingPushed();
});

it('soft-deletes an outlet by deactivating it', function () {
    $o = dm_outlet();

    $this->deleteJson("/api/master/outlets/{$o->id}")->assertOk()->assertJsonPath('is_active', false);

    expect(Outlet::find($o->id))->not->toBeNull()->and($o->fresh()->is_active)->toBeFalse();
    $this->getJson('/api/map/outlets')->assertJsonPath('count', 0);
});

it('imports outlets from CSV: creates, updates and reports row errors', function () {
    $fake = dm_fake_reconciler();
    Queue::fake();
    $jk = Province::factory()->create(['name' => 'DKI Jakarta', 'code' => 'JK']);
    Province::factory()->create(['name' => 'Bali', 'code' => 'BA']);
    dm_outlet(['code' => 'OUT-001', 'name' => 'Nama Lama', 'province_id' => $jk->id]);

    $csv = implode("\n", [
        'code,name,address,city,province,lat,lng',
        'OUT-001,Toko Sinar Jaya,Jl. Melati 1,Jakarta Pusat,DKI Jakarta,-6.2,106.8',   // update with coords
        'OUT-002,Warung Bu Tini,Jl. Mawar 2,Denpasar,Bali,-8.65,115.2',                  // create with coords
        'OUT-003,Kedai Tanpa Koordinat,,Denpasar,ba,,',                                   // create, geocode later (province by code)
        'OUT-004,Provinsi Salah,,Kota,Atlantis,,',                                        // error
        'OUT-005,,,Kota,Bali,,',                                                          // error: no name
        'OUT-006,Koordinat Rusak,,Kota,Bali,abc,106',                                     // error
        'OUT-007,Satu Koordinat,,Kota,Bali,-8.6,',                                        // error
    ]);

    $r = $this->postJson('/api/master/outlets/import', ['file' => UploadedFile::fake()->createWithContent('outlets.csv', $csv)])->assertOk();

    $r->assertJsonPath('created', 2)->assertJsonPath('updated', 1)->assertJsonPath('geocoded', 2)->assertJsonPath('failed', 4)->assertJsonPath('needs_review', 0)
        ->assertJsonPath('queued', 1)
        ->assertJsonStructure(['created', 'updated', 'geocoded', 'needs_review', 'failed', 'errors' => [['row', 'message']]]);
    expect(array_column($r->json('errors'), 'row'))->toBe([5, 6, 7, 8]);
    expect(Outlet::where('code', 'OUT-001')->first()->name)->toBe('Toko Sinar Jaya')
        ->and(Outlet::where('code', 'OUT-002')->first())->toMatchArray(['geocode_source' => 'csv', 'is_simulated' => false])
        ->and(Outlet::where('code', 'OUT-003')->first()->lat)->toBeNull()
        ->and(Outlet::where('code', 'OUT-004')->exists())->toBeFalse();
    Queue::assertPushed(GeocodeOutletJob::class, 1);
    expect($fake->calls)->toHaveCount(1)->and($fake->calls[0])->toHaveCount(2);
    expect(Cache::get('hunter.whitespace.version'))->toBe(2);
});

it('rejects CSV files that are too big, wrong type or missing columns', function () {
    $this->postJson('/api/master/outlets/import', [])->assertStatus(422)->assertJsonValidationErrors('file');
    $this->postJson('/api/master/outlets/import', ['file' => UploadedFile::fake()->create('big.csv', 3000, 'text/csv')])->assertStatus(422)->assertJsonValidationErrors('file');
    $this->postJson('/api/master/outlets/import', ['file' => UploadedFile::fake()->create('x.exe', 10, 'application/octet-stream')])->assertStatus(422)->assertJsonValidationErrors('file');
    $this->postJson('/api/master/outlets/import', ['file' => UploadedFile::fake()->createWithContent('o.csv', "code,name\nOUT-1,x")])
        ->assertStatus(422)->assertJsonPath('code', 'missing_columns');
});

it('handles a UTF-8 BOM and blank lines in the CSV', function () {
    Queue::fake();
    dm_fake_reconciler();
    Province::factory()->create(['name' => 'Bali']);

    $csv = "\xEF\xBB\xBFcode,name,address,city,province,lat,lng\n\nOUT-010,Toko Bali,,Denpasar,Bali,-8.6,115.2\n";
    $this->postJson('/api/master/outlets/import', ['file' => UploadedFile::fake()->createWithContent('o.csv', $csv)])->assertOk()->assertJsonPath('created', 1)->assertJsonPath('failed', 0);
});

it('does not fail the import when reconciliation is unavailable (default binding)', function () {
    Queue::fake();
    Province::factory()->create(['name' => 'Bali']);

    $csv = "code,name,address,city,province,lat,lng\nOUT-011,Toko Bali,,Denpasar,Bali,-8.6,115.2\n";
    $this->postJson('/api/master/outlets/import', ['file' => UploadedFile::fake()->createWithContent('o.csv', $csv)])->assertOk()->assertJsonPath('created', 1);
});

it('re-queues forward geocoding for a failed outlet', function () {
    Queue::fake();
    $o = dm_outlet(['geocode_status' => 'failed', 'lat' => null, 'lng' => null]);

    $this->postJson("/api/master/outlets/{$o->id}/retry-geocode")->assertOk()->assertJsonPath('queued', true);

    Queue::assertPushed(GeocodeOutletJob::class, 1);
    expect($o->fresh()->geocode_status->value)->toBe('ok');
});

it('lists the review queue with candidates and resolves by candidate or coordinates', function () {
    $fake = dm_fake_reconciler();
    $candidates = [
        ['display_name' => 'Jl. A, Bandung', 'lat' => -6.91, 'lng' => 107.61, 'score' => 72],
        ['display_name' => 'Jl. B, Bandung', 'lat' => -6.92, 'lng' => 107.62, 'score' => 55],
    ];
    $a = dm_outlet(['code' => 'OUT-A1', 'geocode_status' => 'needs_review', 'lat' => null, 'lng' => null, 'geocode_candidates' => $candidates]);
    $b = dm_outlet(['code' => 'OUT-A2', 'geocode_status' => 'needs_review', 'lat' => null, 'lng' => null, 'geocode_candidates' => $candidates]);
    dm_outlet(['geocode_status' => 'ok']);

    $this->getJson('/api/master/outlets/review')->assertOk()->assertJsonCount(2)
        ->assertJsonPath('0.outlet.id', $a->id)->assertJsonPath('0.candidates.0.display_name', 'Jl. A, Bandung')->assertJsonPath('0.candidates.1.score', 55);

    $this->postJson("/api/master/outlets/{$a->id}/resolve", ['candidate_index' => 1])->assertOk()->assertJsonPath('geocode_status', 'manual');
    expect($a->fresh()->lat)->toBe(-6.92)->and($a->fresh()->geocode_candidates)->toBeNull();

    $this->postJson("/api/master/outlets/{$b->id}/resolve", ['lat' => -6.5, 'lng' => 107.5])->assertOk();
    expect($b->fresh()->lng)->toBe(107.5)->and($b->fresh()->geocode_status->value)->toBe('manual')->and($fake->calls)->toHaveCount(2);

    $this->getJson('/api/master/outlets/review')->assertJsonCount(0);
});

it('rejects an invalid resolve request', function () {
    $o = dm_outlet(['geocode_status' => 'needs_review', 'geocode_candidates' => [['display_name' => 'x', 'lat' => 1, 'lng' => 2, 'score' => 60]]]);

    $this->postJson("/api/master/outlets/{$o->id}/resolve", [])->assertStatus(422);
    $this->postJson("/api/master/outlets/{$o->id}/resolve", ['candidate_index' => 5])->assertStatus(422)->assertJsonPath('code', 'invalid_candidate');
    $this->postJson("/api/master/outlets/{$o->id}/resolve", ['lat' => 200, 'lng' => 0])->assertStatus(422);
});

// ---------------------------------------------------------------- categories

it('lists, creates and updates categories with template validation', function () {
    dm_category(['key' => 'cafe', 'sort_order' => 1]);

    $this->getJson('/api/master/categories')->assertOk()->assertJsonCount(1, 'categories')
        ->assertJsonStructure(['categories' => [['id', 'key', 'name', 'weight', 'whatsapp_template', 'osm_filters', 'is_active']]]);

    $created = $this->postJson('/api/master/categories', [
        'key' => 'toko_buku', 'name' => 'Toko Buku', 'weight' => 60, 'whatsapp_template' => 'Halo {nama_bisnis}, saya {nama_pengirim}.', 'osm_filters' => ['shop' => ['books']],
    ])->assertCreated()->assertJsonPath('key', 'toko_buku')->assertJsonPath('is_active', true);

    $id = $created->json('id');
    $this->putJson("/api/master/categories/{$id}", [
        'key' => 'toko_buku', 'name' => 'Toko Buku & Alat Tulis', 'weight' => 65, 'whatsapp_template' => 'Halo {nama_bisnis} ({kategori})', 'is_active' => false,
    ])->assertOk()->assertJsonPath('name', 'Toko Buku & Alat Tulis')->assertJsonPath('weight', 65)->assertJsonPath('is_active', false);
});

it('rejects invalid categories: placeholders, weight, key', function () {
    $existing = dm_category(['key' => 'cafe']);
    $valid = ['key' => 'baru', 'name' => 'Baru', 'weight' => 50, 'whatsapp_template' => 'Halo {nama_bisnis}'];

    $this->postJson('/api/master/categories', ['whatsapp_template' => 'Tanpa placeholder'] + $valid)->assertStatus(422)->assertJsonValidationErrors('whatsapp_template');
    $this->postJson('/api/master/categories', ['whatsapp_template' => 'Halo {nama_bisnis} {rahasia}'] + $valid)->assertStatus(422)->assertJsonValidationErrors('whatsapp_template');
    $this->postJson('/api/master/categories', ['weight' => 101] + $valid)->assertStatus(422)->assertJsonValidationErrors('weight');
    $this->postJson('/api/master/categories', ['weight' => -1] + $valid)->assertStatus(422);
    $this->postJson('/api/master/categories', ['key' => 'Bad Key!'] + $valid)->assertStatus(422)->assertJsonValidationErrors('key');
    $this->postJson('/api/master/categories', ['key' => 'cafe'] + $valid)->assertStatus(422)->assertJsonValidationErrors('key');
    $this->putJson("/api/master/categories/{$existing->id}", ['key' => 'cafe', 'name' => 'Kafe', 'weight' => 80, 'whatsapp_template' => 'Halo {nama_bisnis}'])->assertOk(); // own key is fine
    expect(Category::count())->toBe(1);
});

// ---------------------------------------------------------------- scheduler

it('lists scheduler runs and shows one with its outlets', function () {
    $o = dm_outlet(['name' => 'Toko A']);
    $run = SchedulerRun::create(['command' => 'hunter:sync', 'status' => 'success', 'started_at' => now()->subHour(), 'finished_at' => now(), 'duration_ms' => 3600000, 'outlets_total' => 1, 'outlets_ok' => 1, 'prospects_new' => 5, 'api_calls' => 2]);
    SchedulerRunOutlet::create(['scheduler_run_id' => $run->id, 'outlet_id' => $o->id, 'provider' => 'osm', 'status' => 'ok', 'fetched_count' => 7, 'new_count' => 5]);
    SchedulerRun::create(['command' => 'hunter:geocode', 'status' => 'failed', 'started_at' => now()->subDay(), 'error' => 'boom']);

    $r = $this->getJson('/api/scheduler/runs')->assertOk();
    $r->assertJsonStructure(['data' => [['id', 'command', 'status', 'started_at', 'finished_at', 'duration_ms', 'outlets_total', 'outlets_ok', 'outlets_failed',
        'prospects_new', 'prospects_updated', 'prospects_closed', 'api_calls', 'error']], 'meta' => ['current_page', 'last_page', 'total', 'per_page']])
        ->assertJsonPath('data.0.id', $run->id)->assertJsonPath('meta.total', 2);

    $this->getJson("/api/scheduler/runs/{$run->id}")->assertOk()->assertJsonPath('outlets.0.outlet', 'Toko A')->assertJsonPath('outlets.0.new_count', 5)->assertJsonPath('prospects_new', 5);
    $this->getJson('/api/scheduler/runs/999')->assertNotFound();
    $this->getJson('/api/scheduler/runs?page=0')->assertStatus(422);
});
