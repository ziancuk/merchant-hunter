<?php

use App\DTO\ScoreInput;
use App\Enums\ScoreBand;
use App\Models\DataSource;
use App\Models\Outlet;
use App\Models\ProspectActivity;
use App\Models\Setting;
use App\Services\Scoring\ScoreCalculator;
use App\Support\ActivityText;
use App\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/../Domain/helpers.php';

const EN = ['X-Locale' => 'en'];

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(8, 0));
    $this->user = dm_login(['name' => 'Ayu']);
});

// ---------------------------------------------------------------- locale resolution

it('resolves only supported locales and falls back to Indonesian', function () {
    $req = fn (array $headers = [], array $cookies = []) => Request::create('/', 'GET', [], $cookies, [], collect($headers)->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all());

    expect(Locale::resolve($req(['X-Locale' => 'en'])))->toBe('en')
        ->and(Locale::resolve($req(['X-Locale' => 'id'])))->toBe('id')
        ->and(Locale::resolve($req(['X-Locale' => 'fr'])))->toBe('id')
        ->and(Locale::resolve($req(['X-Locale' => 'EN'])))->toBe('id')
        ->and(Locale::resolve($req([], [Locale::COOKIE => 'fr'])))->toBe('id')
        ->and(Locale::resolve($req(['X-Locale' => 'fr'], [Locale::COOKIE => 'en'])))->toBe('en')
        ->and(Locale::resolve($req()))->toBe('id');
});

it('ignores an unsupported X-Locale on a real request', function () {
    $this->getJson('/api/does-not-exist', ['X-Locale' => 'fr'])->assertNotFound()->assertExactJson(['message' => 'Data tidak ditemukan.']);
});

// ---------------------------------------------------------------- framework JSON errors

it('answers 404 JSON in the requested language', function () {
    $this->getJson('/api/does-not-exist')->assertNotFound()->assertExactJson(['message' => 'Data tidak ditemukan.']);
    $this->getJson('/api/does-not-exist', EN)->assertNotFound()->assertExactJson(['message' => 'Data not found.']);
});

it('answers 429 JSON in the requested language', function () {
    Route::middleware(['web', 'throttle:1,1'])->get('/_i18n-throttle', fn () => response()->json(['ok' => true]));

    $this->getJson('/_i18n-throttle', EN)->assertOk();
    $this->getJson('/_i18n-throttle', EN)->assertStatus(429)->assertJsonPath('message', 'Too many requests. Please try again in a moment.');
    $this->getJson('/_i18n-throttle')->assertStatus(429)->assertJsonPath('message', 'Terlalu banyak permintaan. Coba lagi sebentar lagi.');
});

// ---------------------------------------------------------------- Blade error pages

it('renders the static error pages in the resolved language', function () {
    auth()->logout();

    $this->get('/halaman-tak-ada')->assertNotFound()->assertSee('Halaman tidak ditemukan')->assertSee('Kembali ke peta')->assertSee('lang="id"', false);
    $this->get('/halaman-tak-ada', EN)->assertNotFound()->assertSee('Page not found')->assertSee('Back to the map')->assertSee('lang="en"', false)->assertDontSee('Halaman tidak ditemukan');
    $this->withUnencryptedCookie(Locale::COOKIE, 'en')->get('/halaman-tak-ada')->assertSee('Page not found');

    foreach ([401, 403, 419, 429, 500, 503] as $code) {
        $en = view("errors.{$code}")->render();
        expect($en)->not->toContain('lang="id"')->and($en)->toContain((string) $code);
    }
});

// ---------------------------------------------------------------- validation (form requests)

it('translates validation attributes and custom messages', function () {
    $id = $this->putJson('/api/settings', ['contact' => ['warn_days' => 'x']])->assertStatus(422);
    expect($id->json('errors')['contact.warn_days'][0])->toBe('Peringatan kontak terakhir harus berupa bilangan bulat.');

    $en = $this->putJson('/api/settings', ['contact' => ['warn_days' => 'x']], EN)->assertStatus(422);
    expect($en->json('errors')['contact.warn_days'][0])->toBe('Last-contact warning must be a whole number.');

    $this->putJson('/api/settings/impact', ['outlets' => 'abc'])->assertStatus(422)->assertJsonPath('errors.outlets.0', 'Jumlah outlet harus berupa bilangan bulat.');
    $this->putJson('/api/settings/impact', ['outlets' => 'abc'], EN)->assertStatus(422)->assertJsonPath('errors.outlets.0', 'Number of outlets must be a whole number.');

    $this->getJson('/api/search?q=a')->assertStatus(422)->assertJsonPath('errors.q.0', 'Kata kunci minimal 2 karakter.');
    $this->getJson('/api/search?q=a', EN)->assertStatus(422)->assertJsonPath('errors.q.0', 'The search term must be at least 2 characters.');

    $this->postJson('/api/master/outlets/import', [])->assertStatus(422)->assertJsonValidationErrors('file');
    $this->postJson('/api/master/outlets/import', ['file' => UploadedFile::fake()->create('big.csv', 3000, 'text/csv')], EN)->assertStatus(422)->assertJsonPath('errors.file.0', 'The file must not exceed 2 MB.');

    $s = dm_data_source_i18n();
    $this->putJson("/api/master/data-sources/{$s->id}", ['config' => ['endpoint' => 'x']])->assertStatus(422)
        ->assertJsonPath('errors.config.0', 'Konfigurasi memuat pengaturan yang tidak dikenal. Yang diizinkan: region, language, radius_m, timeout_s, notes.');
    $this->putJson("/api/master/data-sources/{$s->id}", ['config' => ['endpoint' => 'x']], EN)->assertStatus(422)
        ->assertJsonPath('errors.config.0', 'The configuration contains unknown settings. Allowed: region, language, radius_m, timeout_s, notes.');
});

function dm_data_source_i18n(): DataSource
{
    return DataSource::query()->first() ?? DataSource::create(['key' => 'osm', 'name' => 'OSM', 'is_active' => true, 'priority' => 10]);
}

it('ships natural built-in validation wording in both languages', function () {
    app()->setLocale('en');
    expect(validator(['a' => ''], ['a' => 'required'])->errors()->first('a'))->toBe('The a field is required.');
    app()->setLocale('id');
    expect(validator(['a' => ''], ['a' => 'required'])->errors()->first('a'))->toBe('a wajib diisi.')
        ->and(validator(['a' => 'x'], ['a' => 'min:3'])->errors()->first('a'))->toBe('a harus terdiri dari minimal 3 karakter.')
        ->and(validator(['a' => 'x'], ['a' => 'email'])->errors()->first('a'))->toBe('a harus berupa alamat email yang valid.');
});

// ---------------------------------------------------------------- action / controller messages

it('translates business-rule messages (route planner, contact policy)', function () {
    $a = dm_prospect(['lat' => -6.2, 'lng' => 106.8]);
    $none = dm_prospect(['lat' => null, 'lng' => null]);

    $this->postJson('/api/routes/plan', ['prospect_ids' => [$a->id, $none->id]])->assertStatus(422)
        ->assertJsonPath('message', 'Minimal 2 prospek dengan koordinat diperlukan untuk menyusun rute.')
        ->assertJsonPath('errors.prospect_ids.0', 'Minimal 2 prospek dengan koordinat diperlukan.');
    $this->postJson('/api/routes/plan', ['prospect_ids' => [$a->id, $none->id]], EN)->assertStatus(422)
        ->assertJsonPath('message', 'At least 2 prospects with coordinates are needed to plan a route.')
        ->assertJsonPath('code', 'not_enough_stops');

    $closed = dm_prospect(['phone' => '081255550101', 'lifecycle_status' => 'closed']);
    $this->postJson("/api/prospects/{$closed->id}/contact", ['message' => 'Halo'])->assertStatus(422)->assertJsonPath('message', 'Usaha ini sudah tutup.');
    $this->postJson("/api/prospects/{$closed->id}/contact", ['message' => 'Hello'], EN)->assertStatus(422)->assertJsonPath('message', 'This business has closed.');
});

it('turns the AI-disabled abort and the route estimate note into the request language', function () {
    config(['hunter.ai.key' => '']);
    $p = dm_prospect(['lat' => -6.2, 'lng' => 106.8]);
    $q = dm_prospect(['lat' => -6.21, 'lng' => 106.81]);

    $this->postJson("/api/prospects/{$p->id}/message-ai", [])->assertNotFound()->assertJsonPath('message', 'Data tidak ditemukan.');

    $plan = $this->postJson('/api/routes/plan', ['prospect_ids' => [$p->id, $q->id]])->assertOk();
    expect($plan->json('estimate_note'))->toBe('Estimasi garis lurus; jarak jalan sebenarnya lebih panjang.');
    $plan = $this->postJson('/api/routes/plan', ['prospect_ids' => [$p->id, $q->id]], EN)->assertOk();
    expect($plan->json('estimate_note'))->toBe('Straight-line estimate; the actual road distance is longer.');
});

it('translates CSV import row errors', function () {
    $csv = implode("\n", ['code,name,address,city,province,lat,lng', 'OUT-9,Satu Koordinat,,Kota,Atlantis,,']);
    $file = fn () => UploadedFile::fake()->createWithContent('outlets.csv', $csv);

    $id = $this->postJson('/api/master/outlets/import', ['file' => $file()])->assertOk();
    expect($id->json('errors.0.message'))->toBe('Provinsi "Atlantis" tidak dikenal.');
    $en = $this->postJson('/api/master/outlets/import', ['file' => $file()], EN)->assertOk();
    expect($en->json('errors.0.message'))->toBe('Province "Atlantis" is not recognised.');
});

it('translates the login failure message', function () {
    auth()->logout();
    $this->post('/login', ['email' => 'x@hunter.test', 'password' => 'salah'])->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
    auth()->logout();
    $this->post('/login', ['email' => 'y@hunter.test', 'password' => 'wrong'], EN)->assertSessionHasErrors(['email' => 'Incorrect email or password.']);
});

// ---------------------------------------------------------------- connection test messages

it('translates connection-test messages', function () {
    $s = DataSource::query()->where('key', 'google')->first()
        ?? DataSource::create(['key' => 'google', 'name' => 'Google', 'is_active' => true, 'priority' => 5]);

    $this->postJson("/api/master/data-sources/{$s->id}/test")->assertOk()->assertJsonPath('message', 'API key Google belum diisi. Simpan API key lebih dulu.');
    $this->postJson("/api/master/data-sources/{$s->id}/test", [], EN)->assertOk()->assertJsonPath('message', 'The Google API key is empty. Save an API key first.');
});

// ---------------------------------------------------------------- score breakdown, bands, activities

function i18n_score_input(): ScoreInput
{
    return new ScoreInput(85, 3.6, 430, true, 640, 3, 7, true, false, true, true, 4);
}

it('writes score reasons and labels in the current language and keeps a structured form', function () {
    $calc = new ScoreCalculator(pure: true);

    $id = collect($calc->score(i18n_score_input())->breakdown)->keyBy('key');
    expect($id['category']['label'])->toBe('Kecocokan kategori')
        ->and($id['category']['reason'])->toBe('Bobot kategori 85')
        ->and($id['quality']['reason'])->toBe('Rating 3.6 dari 430 ulasan')
        ->and($id['proximity']['reason'])->toBe('3 outlet Hitachi dalam 500 m; outlet terdekat 640 m')
        ->and($id['density']['reason'])->toBe('7 prospek lain dalam 500 m')
        ->and($id['contact']['reason'])->toBe('Tersedia: telepon, alamat')
        ->and($id['new']['reason'])->toBe('Baru buka 4 hari lalu');

    app()->setLocale('en');
    $en = collect($calc->score(i18n_score_input())->breakdown)->keyBy('key');
    expect($en['category']['label'])->toBe('Category fit')
        ->and($en['quality']['reason'])->toBe('Rating 3.6 from 430 reviews')
        ->and($en['proximity']['reason'])->toBe('3 Hitachi outlets within 500 m; nearest outlet 640 m')
        ->and($en['contact']['reason'])->toBe('Available: phone, address')
        ->and($en['new']['reason'])->toBe('Opened 4 days ago');
});

it('re-translates the stored breakdown when a prospect detail is read', function () {
    app()->setLocale('id');
    $breakdown = (new ScoreCalculator(pure: true))->score(i18n_score_input())->breakdown;
    $legacy = ['key' => 'density', 'label' => 'Kepadatan area', 'value' => 0.1, 'max' => 15, 'points' => 1.5, 'reason' => 'Teks lama'];
    $p = dm_prospect(['score' => 70, 'score_breakdown' => [...$breakdown, $legacy]]);

    $id = $this->getJson("/api/prospects/{$p->id}")->assertOk()->json('score_breakdown');
    expect($id[0]['label'])->toBe('Kecocokan kategori')->and($id[0]['reason'])->toBe('Bobot kategori 85')
        ->and($id[0])->not->toHaveKeys(['reason_key', 'reason_params']);

    $en = $this->getJson("/api/prospects/{$p->id}", EN)->assertOk()->json('score_breakdown');
    expect($en[0]['label'])->toBe('Category fit')->and($en[0]['reason'])->toBe('Category weight 85')
        ->and($en[2]['reason'])->toBe('3 Hitachi outlets within 500 m; nearest outlet 640 m')
        ->and($en[6]['label'])->toBe('Area density')->and($en[6]['reason'])->toBe('Teks lama');
});

it('names the score bands Bidik Utama, Menjanjikan, Berpotensi and Pantau (Prime Target ...)', function () {
    expect(ScoreBand::Hot->label())->toBe('Bidik Utama')
        ->and(ScoreBand::Warm->label())->toBe('Menjanjikan')
        ->and(ScoreBand::Medium->label())->toBe('Berpotensi')
        ->and(ScoreBand::Cold->label())->toBe('Pantau');
    app()->setLocale('en');
    expect(ScoreBand::Hot->label())->toBe('Prime Target')
        ->and(ScoreBand::Warm->label())->toBe('Promising')
        ->and(ScoreBand::Medium->label())->toBe('Potential')
        ->and(ScoreBand::Cold->label())->toBe('Watch');
});

it('localizes system activity bodies on read and never touches free-form notes', function () {
    $p = dm_prospect();
    $sys = ProspectActivity::forceCreate(['prospect_id' => $p->id, 'type' => 'contacted', 'body' => 'Dihubungi lewat WhatsApp']);
    ProspectActivity::forceCreate(['prospect_id' => $p->id, 'type' => 'system', 'body' => 'Ditutup otomatis: cocok dengan merchant Hitachi Toko A (OUT-1).']);
    ProspectActivity::forceCreate(['prospect_id' => $p->id, 'type' => 'note', 'body' => 'Dihubungi lewat WhatsApp']);

    expect($sys->body)->toBe('Dihubungi lewat WhatsApp');
    $en = collect($this->getJson("/api/prospects/{$p->id}", EN)->assertOk()->json('activities'))->pluck('body', 'type');
    expect($en['contacted'])->toBe('Contacted via WhatsApp')
        ->and($en['system'])->toBe('Closed automatically: matches Hitachi merchant Toko A (OUT-1).')
        ->and($en['note'])->toBe('Dihubungi lewat WhatsApp');

    app()->setLocale('id');
    expect(ActivityText::localize('Contacted via WhatsApp'))->toBe('Dihubungi lewat WhatsApp')
        ->and(ActivityText::localize('Rejected: Not interested'))->toBe('Ditolak: Not interested')
        ->and(ActivityText::localize('Catatan bebas'))->toBe('Catatan bebas');
});

it('writes new system activities in the request language', function () {
    $p = dm_prospect(['phone' => '081255550101']);

    $this->postJson("/api/prospects/{$p->id}/opt-out", [], EN)->assertOk();
    expect(ProspectActivity::query()->where('prospect_id', $p->id)->where('type', 'opt_out')->value('body'))->toBe('Marked do-not-contact (opt-out)');
});

// ---------------------------------------------------------------- digest

it('builds the digest e-mail and command summary in the active language', function () {
    dm_prospect(['name' => 'Panas Satu', 'score' => 90]);
    Setting::write('digest.email_to', 'boss@hunter.test');
    config(['mail.default' => 'array']);

    $this->artisan('hunter:digest')->expectsOutputToContain('Digest 2026-10-24 dibuat: 0 baru buka, 1 bidik utama, 0 follow-up.')->assertSuccessful();
    $id = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
    expect($id->getSubject())->toBe('Ringkasan harian prospek 2026-10-24')
        ->and($id->getTextBody())->toContain('Prospek Bidik Utama (1)')->toContain('- Panas Satu (skor 90)');

    app()->setLocale('en');
    $this->artisan('hunter:digest', ['--date' => '2026-10-23'])->expectsOutputToContain('Digest 2026-10-23 created: 0 newly opened, 1 prime target, 0 follow-up.')->assertSuccessful();
    $en = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
    expect($en->getSubject())->toBe('Daily prospect digest 2026-10-23')
        ->and($en->getTextBody())->toContain('Prime Target prospects (1)')->toContain('- Panas Satu (score 90)');
});

// ---------------------------------------------------------------- exports

it('exports Indonesian headers by default and English headers for X-Locale en', function () {
    $outlet = dm_outlet();
    dm_prospect(['name' => 'Kopi Senja', 'score' => 82]);
    expect($outlet)->toBeInstanceOf(Outlet::class);

    $idRes = $this->get('/prospects/export.csv');
    expect($idRes->streamedContent())->toStartWith("\xEF\xBB\xBFid,nama,kategori,kota,provinsi,alamat,telepon,simulasi_telepon,rating,jumlah_review,skor,status,ditangani_oleh,terakhir_dihubungi,jarak_outlet_m\n")
        ->and($idRes->headers->get('content-disposition'))->toContain('prospek-20261024.csv');

    $enRes = $this->get('/prospects/export.csv', EN);
    expect($enRes->streamedContent())->toStartWith("\xEF\xBB\xBFid,name,category,city,province,address,phone,phone_simulated,rating,review_count,score,status,handled_by,last_contacted,distance_to_outlet_m\n")
        ->and($enRes->headers->get('content-disposition'))->toContain('prospects-20261024.csv');

    expect($this->get('/prospects/export.xlsx', EN)->headers->get('content-disposition'))->toContain('prospects-20261024.xlsx');
    expect($this->get('/prospects/export.xlsx')->headers->get('content-disposition'))->toContain('prospek-20261024.xlsx');

    $dash = explode("\n", $this->get('/dashboard/export.csv', EN)->streamedContent());
    expect($dash[0])->toBe("\xEF\xBB\xBFsection,name,value,prospects,won,penetration")->and($dash)->toContain('KPI,outlets,1,,,');
    expect($this->get('/dashboard/export.csv', EN)->headers->get('content-disposition'))->toContain('dashboard-20261024.csv');
});
