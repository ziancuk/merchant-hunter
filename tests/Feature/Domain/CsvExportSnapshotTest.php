<?php

use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(8, 0));
    $this->user = dm_login(['name' => 'Ayu']);
});

/** Fixture exercising every column: nulls, whole-number ratings, handler, UTC timestamp, quoting and formula cells. */
function cs_fixture(): void
{
    $jakarta = dm_province(['name' => 'DKI Jakarta']);
    $kafe = dm_category(['key' => 'kafe', 'name' => 'Kafe']);
    $bengkel = dm_category(['key' => 'bengkel', 'name' => 'Bengkel']);

    dm_prospect([
        'name' => 'Kopi Senja', 'category_id' => $kafe->id, 'province_id' => $jakarta->id, 'city' => 'Jakarta Pusat', 'address' => 'Jl. Melati 4',
        'phone' => '0812-5555-0101', 'phone_simulated' => true, 'rating' => 4.6, 'review_count' => 312, 'score' => 82,
        'pipeline_status' => 'contacted', 'handled_by' => test()->user->id, 'last_contacted_at' => '2026-10-23 08:00:00', 'nearest_outlet_distance_m' => 640,
    ]);
    dm_prospect([
        'name' => 'Bengkel "Jaya", Motor', 'category_id' => $bengkel->id, 'province_id' => null, 'city' => null, 'address' => null,
        'phone' => null, 'phone_simulated' => false, 'rating' => 4.0, 'review_count' => null, 'score' => 40,
        'pipeline_status' => 'new', 'nearest_outlet_distance_m' => null,
    ]);
    dm_prospect([
        'name' => '=HYPERLINK("http://evil.test","x")', 'category_id' => $kafe->id, 'province_id' => $jakarta->id, 'city' => '@cmd', 'address' => '+SUM(1+1)',
        'phone' => '-2+3', 'rating' => null, 'score' => 10, 'pipeline_status' => 'lost', 'nearest_outlet_distance_m' => 0,
    ]);
    dm_prospect(['name' => 'Tutup', 'category_id' => $kafe->id, 'lifecycle_status' => 'closed', 'score' => 99]);
}

const CS_HEADER = "\xEF\xBB\xBFid,nama,kategori,kota,provinsi,alamat,telepon,simulasi_telepon,rating,jumlah_review,skor,status,ditangani_oleh,terakhir_dihubungi,jarak_outlet_m\n";

it('keeps the prospect CSV byte-identical (columns, formatting, sanitisation, ordering)', function () {
    cs_fixture();

    $csv = $this->get('/prospects/export.csv')->assertOk()->streamedContent();

    expect($csv)->toBe(CS_HEADER
        ."1,\"Kopi Senja\",Kafe,\"Jakarta Pusat\",\"DKI Jakarta\",\"Jl. Melati 4\",0812-5555-0101,1,4.6,312,82,contacted,Ayu,2026-10-23T08:00:00Z,640\n"
        ."2,\"Bengkel \"\"Jaya\"\", Motor\",Bengkel,,,,,0,4,,40,new,,,\n"
        ."3,\"'=HYPERLINK(\"\"http://evil.test\"\",\"\"x\"\")\",Kafe,'@cmd,\"DKI Jakarta\",'+SUM(1+1),'-2+3,0,,,10,lost,,,0\n");
});

it('keeps filter semantics and includes closed rows on request', function () {
    cs_fixture();

    $csv = $this->get('/prospects/export.csv?include_closed=1&score_min=90')->streamedContent();

    expect($csv)->toBe(CS_HEADER."4,Tutup,Kafe,,,,,0,,,99,new,,,\n");
});

it('does not hydrate Eloquent models nor run N+1 queries for the prospect export', function () {
    cs_fixture();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->get('/prospects/export.csv')->streamedContent();

    $selects = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "prospects"'))->count();
    expect($selects)->toBe(1);
    expect(collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "categories"') || str_contains($q['query'], 'from "users" where'))->count())->toBe(0);
});

it('keeps the dashboard CSV byte-identical', function () {
    dm_province(['id' => 31, 'name' => 'DKI Jakarta']);
    dm_prospect(['contacted_at' => now(), 'won_at' => now(), 'pipeline_status' => 'won']);

    $csv = $this->get('/dashboard/export.csv')->assertOk()->streamedContent();

    $lines = array_values(array_filter(explode("\n", $csv)));
    expect($lines[0])->toBe("\xEF\xBB\xBFbagian,nama,nilai,prospek,menang,penetrasi")
        ->and($lines)->toContain('KPI,outlets,0,,,')
        ->and($lines)->toContain('KPI,conversion_rate,1,,,')
        ->and($lines)->toContain('Funnel,found,1,,,')
        ->and($lines)->toContain('Funnel,lost,0,,,');
});
