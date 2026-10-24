<?php

use App\Models\DailyDigest;
use App\Models\ProspectActivity;
use App\Models\SchedulerRun;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    Cache::flush();
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(8, 0));
    $this->user = dm_login(['name' => 'Ayu']);
});

function dm_digest_fixture(): array
{
    $f = [];
    $f['new_open'] = dm_prospect(['name' => 'Baru Buka', 'is_new_open' => true, 'is_baseline' => false, 'score' => 50, 'first_seen_at' => now()->subDay()]);
    $f['baseline'] = dm_prospect(['name' => 'Baseline', 'is_new_open' => true, 'is_baseline' => true, 'score' => 50]);
    $f['hot'] = dm_prospect(['name' => 'Panas', 'score' => 90]);
    $f['hot_optout'] = dm_prospect(['name' => 'Panas Opt Out', 'score' => 95, 'do_not_contact' => true]);
    $f['hot_closed'] = dm_prospect(['name' => 'Panas Tutup', 'score' => 95, 'lifecycle_status' => 'closed']);
    $f['follow_old'] = dm_prospect(['name' => 'Follow Lama', 'score' => 30, 'pipeline_status' => 'contacted']);
    ProspectActivity::forceCreate(['prospect_id' => $f['follow_old']->id, 'type' => 'contacted', 'created_at' => now()->subDays(10)]);
    $f['follow_recent'] = dm_prospect(['name' => 'Follow Baru', 'score' => 30, 'pipeline_status' => 'contacted']);
    ProspectActivity::forceCreate(['prospect_id' => $f['follow_recent']->id, 'type' => 'contacted', 'created_at' => now()->subDays(2)]);
    $f['follow_optout'] = dm_prospect(['name' => 'Follow Opt Out', 'pipeline_status' => 'demo', 'do_not_contact' => true]);
    $f['follow_none'] = dm_prospect(['name' => 'Demo Tanpa Aktivitas', 'score' => 20, 'pipeline_status' => 'demo']);
    $f['won'] = dm_prospect(['name' => 'Menang', 'score' => 99, 'pipeline_status' => 'won']);

    return $f;
}

it('builds the digest: new open (not baseline), hot, follow-up (no activity beyond the window)', function () {
    dm_digest_fixture();

    $this->artisan('hunter:digest')->assertSuccessful();

    $payload = DailyDigest::firstOrFail()->payload;
    expect($payload['date'])->toBe('2026-10-24')
        ->and(array_column($payload['new_open'], 'name'))->toBe(['Baru Buka'])
        ->and(array_column($payload['hot'], 'name'))->toBe(['Panas'])
        ->and(array_column($payload['follow_up'], 'name'))->toBe(['Follow Lama', 'Demo Tanpa Aktivitas'])
        ->and($payload['hot'][0])->toHaveKeys(['id', 'name', 'score']);
});

it('respects the configured hot threshold and follow-up window', function () {
    dm_digest_fixture();
    Setting::write('digest.hot_threshold', 99);
    Setting::write('digest.follow_up_days', 1);

    $this->artisan('hunter:digest')->assertSuccessful();

    $payload = DailyDigest::firstOrFail()->payload;
    expect($payload['hot'])->toBe([])  // the 99-score prospect is already "won"; the new ones are < 99
        ->and(array_column($payload['follow_up'], 'name'))->toBe(['Follow Lama', 'Follow Baru', 'Demo Tanpa Aktivitas']);
});

it('is idempotent per date and only rebuilds with --force', function () {
    dm_digest_fixture();
    $this->artisan('hunter:digest')->assertSuccessful();
    dm_prospect(['name' => 'Panas Kedua', 'score' => 88]);

    $this->artisan('hunter:digest')->assertSuccessful();
    expect(DailyDigest::count())->toBe(1)->and(count(DailyDigest::first()->payload['hot']))->toBe(1);

    $this->artisan('hunter:digest', ['--force' => true])->assertSuccessful();
    expect(DailyDigest::count())->toBe(1)->and(count(DailyDigest::first()->payload['hot']))->toBe(2);

    $this->artisan('hunter:digest', ['--date' => '2026-10-23'])->assertSuccessful();
    expect(DailyDigest::count())->toBe(2);
});

it('records a scheduler run for the digest command', function () {
    $this->artisan('hunter:digest')->assertSuccessful();

    $run = SchedulerRun::where('command', 'hunter:digest')->firstOrFail();
    expect($run->status)->toBe('success')->and($run->finished_at)->not->toBeNull();
});

it('e-mails the digest only when an address is set and a real mailer is configured', function () {
    dm_digest_fixture();
    $this->artisan('hunter:digest')->assertSuccessful();
    expect(DailyDigest::first()->emailed_at)->toBeNull();

    Setting::write('digest.email_to', 'boss@example.test');
    config(['mail.default' => 'log']);
    $this->artisan('hunter:digest')->assertSuccessful();
    expect(DailyDigest::first()->emailed_at)->toBeNull();

    config(['mail.default' => 'array']);
    $this->artisan('hunter:digest')->assertSuccessful();
    expect(DailyDigest::first()->emailed_at)->not->toBeNull();

    $messages = app('mailer')->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(1)->and($messages[0]->getOriginalMessage()->getTo()[0]->getAddress())->toBe('boss@example.test');
});

it('serves the digest through the API: live for today, stored for past dates, empty otherwise', function () {
    dm_digest_fixture();

    $today = $this->getJson('/api/digest')->assertOk();
    $today->assertJsonStructure(['date', 'new_open', 'hot', 'follow_up'])->assertJsonPath('date', '2026-10-24')->assertJsonPath('hot.0.name', 'Panas');

    DailyDigest::create(['digest_date' => '2026-10-20', 'payload' => ['date' => '2026-10-20', 'new_open' => [], 'hot' => [['id' => 1, 'name' => 'Lama', 'score' => 80]], 'follow_up' => []]]);
    $this->getJson('/api/digest?date=2026-10-20')->assertOk()->assertJsonPath('hot.0.name', 'Lama');
    $this->getJson('/api/digest?date=2026-10-01')->assertOk()->assertExactJson(['date' => '2026-10-01', 'new_open' => [], 'hot' => [], 'follow_up' => []]);
    $this->getJson('/api/digest?date=bogus')->assertStatus(422);
});

// ---------------------------------------------------------------- routes

it('plans a visit route with order, stops, path and Google Maps links', function () {
    $a = dm_prospect(['name' => 'A', 'lat' => -6.20, 'lng' => 106.80]);
    $b = dm_prospect(['name' => 'B', 'lat' => -6.21, 'lng' => 106.81]);
    $c = dm_prospect(['name' => 'C', 'lat' => -6.205, 'lng' => 106.805]);

    $r = $this->postJson('/api/routes/plan', ['prospect_ids' => [$a->id, $b->id, $c->id]])->assertOk();

    $r->assertJsonStructure(['order', 'stops' => [['id', 'name', 'lat', 'lng', 'leg_km']], 'total_km', 'estimate_note', 'path', 'google_maps_urls'])
        ->assertJsonPath('order', [$a->id, $c->id, $b->id])->assertJsonPath('stops.0.leg_km', 0)->assertJsonPath('path.0', [106.8, -6.2]);
    expect($r->json('google_maps_urls'))->toHaveCount(1)
        ->and($r->json('google_maps_urls.0'))->toContain('https://www.google.com/maps/dir/?api=1&origin=-6.2,106.8&destination=-6.21,106.81&waypoints=-6.205,106.805')
        ->and($r->json('total_km'))->toBeGreaterThan(1.0)->toBeLessThan(2.5);
});

it('starts the route from an explicit point', function () {
    $a = dm_prospect(['lat' => -6.20, 'lng' => 106.80]);
    $b = dm_prospect(['lat' => -6.30, 'lng' => 106.90]);

    $r = $this->postJson('/api/routes/plan', ['prospect_ids' => [$a->id, $b->id], 'start' => ['lat' => -6.31, 'lng' => 106.91]])->assertOk();

    $r->assertJsonPath('order', [$b->id, $a->id])->assertJsonCount(2, 'stops');
    expect($r->json('stops.0.leg_km'))->toBeGreaterThan(0)->and($r->json('google_maps_urls.0'))->toContain('origin=-6.31,106.91');
});

it('splits routes of more than 9 waypoints into several Google Maps URLs', function () {
    $ids = [];
    foreach (range(0, 14) as $i) {
        $ids[] = dm_prospect(['lat' => -6.2 + $i / 1000, 'lng' => 106.8])->id;
    }

    $r = $this->postJson('/api/routes/plan', ['prospect_ids' => $ids])->assertOk();

    expect($r->json('google_maps_urls'))->toHaveCount(2)->and($r->json('order'))->toHaveCount(15);
});

it('validates route planning input', function () {
    $a = dm_prospect(['lat' => -6.2, 'lng' => 106.8]);
    $noCoords = dm_prospect(['lat' => null, 'lng' => null]);

    $this->postJson('/api/routes/plan', ['prospect_ids' => [$a->id]])->assertStatus(422)->assertJsonValidationErrors('prospect_ids');
    $this->postJson('/api/routes/plan', ['prospect_ids' => range(1, 31)])->assertStatus(422)->assertJsonValidationErrors('prospect_ids');
    $this->postJson('/api/routes/plan', ['prospect_ids' => [$a->id, $a->id]])->assertStatus(422);
    $this->postJson('/api/routes/plan', ['prospect_ids' => [$a->id, $noCoords->id]])->assertStatus(422)->assertJsonPath('code', 'not_enough_stops');
    $this->postJson('/api/routes/plan', ['prospect_ids' => [$a->id, 5], 'start' => ['lat' => 'x']])->assertStatus(422);
});

// ---------------------------------------------------------------- CSV export

it('exports prospects as CSV with the contract columns', function () {
    $prov = dm_province(['name' => 'DKI Jakarta']);
    $cat = dm_category(['name' => 'Kafe']);
    dm_prospect([
        'name' => 'Kopi Senja', 'category_id' => $cat->id, 'province_id' => $prov->id, 'city' => 'Jakarta Pusat', 'address' => 'Jl. Melati 4',
        'phone' => '0812-5555-0101', 'phone_simulated' => true, 'rating' => 4.6, 'review_count' => 312, 'score' => 82,
        'pipeline_status' => 'contacted', 'handled_by' => $this->user->id, 'last_contacted_at' => now()->subDay(), 'nearest_outlet_distance_m' => 640,
    ]);

    $r = $this->get('/prospects/export.csv')->assertOk();
    $csv = $r->streamedContent();

    expect($r->headers->get('Content-Type'))->toContain('text/csv')
        ->and($r->headers->get('Content-Disposition'))->toContain('attachment')->toContain('.csv');
    $rows = array_map('str_getcsv', array_filter(explode("\n", str_replace("\xEF\xBB\xBF", '', $csv))));
    $rows = array_values($rows);

    expect($rows[0])->toBe(['id', 'nama', 'kategori', 'kota', 'provinsi', 'alamat', 'telepon', 'simulasi_telepon', 'rating', 'jumlah_review', 'skor', 'status', 'ditangani_oleh', 'terakhir_dihubungi', 'jarak_outlet_m'])
        ->and($rows[1][1])->toBe('Kopi Senja')->and($rows[1][2])->toBe('Kafe')->and($rows[1][4])->toBe('DKI Jakarta')
        ->and($rows[1][7])->toBe('1')->and($rows[1][10])->toBe('82')->and($rows[1][11])->toBe('contacted')->and($rows[1][12])->toBe('Ayu')->and($rows[1][14])->toBe('640');
});

it('neutralises CSV injection in exported cells', function () {
    dm_prospect(['name' => '=HYPERLINK("http://evil.test","x")', 'address' => '+SUM(1+1)', 'city' => '@cmd', 'phone' => '-2+3']);

    $csv = $this->get('/prospects/export.csv')->streamedContent();

    expect($csv)->toContain("'=HYPERLINK")->toContain("'+SUM(1+1)")->toContain("'@cmd")->toContain("'-2+3")
        ->and($csv)->not->toContain(',=HYPERLINK')->not->toContain('"=HYPERLINK');
});

it('applies the filters to the prospect export', function () {
    dm_prospect(['name' => 'Tinggi', 'score' => 90]);
    dm_prospect(['name' => 'Rendah', 'score' => 10]);

    $csv = $this->get('/prospects/export.csv?score_min=50')->streamedContent();

    expect($csv)->toContain('Tinggi')->not->toContain('Rendah');
    $this->getJson('/prospects/export.csv?score_min=abc')->assertStatus(422);
});

it('exports the dashboard KPIs, funnel and penetration', function () {
    dm_province(['name' => 'DKI Jakarta']);
    dm_prospect(['contacted_at' => now(), 'won_at' => now()]);

    $r = $this->get('/dashboard/export.csv')->assertOk();
    $csv = $r->streamedContent();

    expect($r->headers->get('Content-Type'))->toContain('text/csv')
        ->and($csv)->toContain('KPI,outlets')->toContain('KPI,conversion_rate,1')->toContain('Funnel,found,1')->toContain('Funnel,won,1')->toContain('Penetrasi,');
});
