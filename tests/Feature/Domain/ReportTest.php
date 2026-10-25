<?php

use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->user = dm_login(['name' => 'Ayu']);
    $jakarta = dm_province(['name' => 'DKI Jakarta']);
    $kafe = dm_category(['key' => 'kafe', 'name' => 'Kafe']);
    $bengkel = dm_category(['key' => 'bengkel', 'name' => 'Bengkel']);

    dm_prospect(['name' => 'Kopi Senja', 'category_id' => $kafe->id, 'province_id' => $jakarta->id, 'city' => 'Jakarta Pusat', 'phone' => '0812-5555-0101', 'phone_simulated' => true,
        'rating' => 4.6, 'review_count' => 312, 'score' => 82, 'pipeline_status' => 'contacted', 'handled_by' => $this->user->id, 'last_contacted_at' => '2026-10-23 08:00:00', 'nearest_outlet_distance_m' => 640]);
    dm_prospect(['name' => 'Bengkel Jaya', 'category_id' => $bengkel->id, 'province_id' => $jakarta->id, 'city' => 'Bekasi', 'phone' => null,
        'rating' => null, 'review_count' => null, 'score' => 40, 'pipeline_status' => 'new', 'nearest_outlet_distance_m' => null]);
    dm_prospect(['name' => '=HYPERLINK("http://evil.test","x")', 'category_id' => $kafe->id, 'province_id' => $jakarta->id, 'city' => 'Depok', 'rating' => 3.0, 'score' => 10, 'pipeline_status' => 'lost', 'nearest_outlet_distance_m' => 120]);
    dm_prospect(['name' => 'Tutup', 'category_id' => $kafe->id, 'lifecycle_status' => 'closed', 'score' => 99]);
});

it('redirects guests away from the report page, table API and Excel export', function (string $path) {
    auth()->logout();
    $this->get($path)->assertRedirect('/login');
})->with(['/reports', '/api/reports/prospects', '/prospects/export.xlsx']);

it('renders the report page with filter options', function () {
    $this->get('/reports')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Report/Index')
        ->has('filter_options.provinces')
        ->has('filter_options.categories', 2));
});

it('lists open prospects with wide columns and pagination meta', function () {
    $this->getJson('/api/reports/prospects')->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('meta.total', 3)->where('meta.per_page', 25)->where('meta.current_page', 1)->where('meta.last_page', 1)
            ->has('data', 3, fn (AssertableJson $row) => $row
                ->where('name', 'Kopi Senja')->where('category', 'Kafe')->where('province', 'DKI Jakarta')->where('city', 'Jakarta Pusat')
                ->where('phone', '0812-5555-0101')->where('phone_simulated', true)->where('rating', 4.6)->where('review_count', 312)
                ->where('score', 82)->where('score_band', 'hot')->where('pipeline_status', 'contacted')->where('handled_by', 'Ayu')
                ->where('last_contacted_at', '2026-10-23T08:00:00Z')->where('distance_m', 640)
                ->etc())
            ->etc());
});

it('sorts by any allowed column and keeps missing values last in both directions', function () {
    $names = fn (string $q) => collect($this->getJson("/api/reports/prospects?$q")->assertOk()->json('data'))->pluck('name')->all();

    expect($names('sort=name&dir=asc')[0])->toBe('=HYPERLINK("http://evil.test","x")');
    expect($names('sort=score&dir=asc'))->toBe(['=HYPERLINK("http://evil.test","x")', 'Bengkel Jaya', 'Kopi Senja']);
    expect($names('sort=distance&dir=asc'))->toBe(['=HYPERLINK("http://evil.test","x")', 'Kopi Senja', 'Bengkel Jaya']);
    expect($names('sort=distance&dir=desc'))->toBe(['Kopi Senja', '=HYPERLINK("http://evil.test","x")', 'Bengkel Jaya']);
    expect($names('sort=rating&dir=desc'))->toBe(['Kopi Senja', '=HYPERLINK("http://evil.test","x")', 'Bengkel Jaya']);
});

it('rejects unknown sort keys and out-of-range paging', function () {
    $this->getJson('/api/reports/prospects?sort=password')->assertStatus(422);
    $this->getJson('/api/reports/prospects?per_page=500')->assertStatus(422);
});

it('applies the shared prospect filter and includes closed rows on request', function () {
    $this->getJson('/api/reports/prospects?pipeline_status[]=new')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Bengkel Jaya');
    $this->getJson('/api/reports/prospects?has_phone=1')->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson('/api/reports/prospects?include_closed=1')->assertOk()->assertJsonPath('meta.total', 4);
});

it('paginates', function () {
    $res = $this->getJson('/api/reports/prospects?per_page=2&page=2&sort=score&dir=desc')->assertOk();
    expect($res->json('data'))->toHaveCount(1)->and($res->json('meta.last_page'))->toBe(2);
});

it('exports the filtered rows as a real .xlsx workbook with text cells', function () {
    $res = $this->get('/prospects/export.xlsx?pipeline_status[]=new&pipeline_status[]=lost')->assertOk();
    expect($res->headers->get('content-type'))->toContain('spreadsheetml.sheet');
    expect($res->headers->get('content-disposition'))->toContain('.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $res->streamedContent());
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $strings = (string) $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();
    unlink($path);

    $all = $sheet.$strings;
    expect($all)->toContain('Bengkel Jaya')->toContain('nama')->toContain('skor')
        ->not->toContain('Kopi Senja')->not->toContain('Tutup')
        // the formula-looking name is stored as a string, never as a formula cell
        ->toContain('=HYPERLINK')->not->toContain('<f>');
});
