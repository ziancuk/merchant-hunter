<?php

use App\Models\DataSource;
use App\Models\ProspectActivity;
use App\Services\Providers\GoogleProvider;
use App\Services\Sync\SyncService;
use App\Support\Redactor;
use Illuminate\Support\Facades\Cache;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    Cache::flush();
    $this->user = dm_login();
});

it('explains why the Google connection test cannot run (B-04)', function () {
    $provider = app(GoogleProvider::class);

    expect($provider->testConnection()->message)->toBe('Sumber data Google belum terdaftar di server ini.');

    $source = DataSource::create(['key' => 'google', 'name' => 'Google', 'is_active' => true, 'priority' => 5]);
    $provider->refreshSource();
    expect($provider->testConnection()->message)->toContain('API key Google belum diisi');

    $source->forceFill(['api_key' => 'AIza-test-key-0000', 'is_active' => false])->save();
    $provider->refreshSource();
    expect($provider->testConnection()->message)->toContain('nonaktif')->not->toContain('API key');
});

it('does not leak internal setting keys in validation messages (B-07)', function () {
    $r = $this->putJson('/api/settings', ['contact' => ['warn_days' => 999]])->assertStatus(422);

    $message = $r->json('errors')['contact.warn_days'][0];
    expect($message)->toContain('Peringatan kontak terakhir')->not->toContain('warn_days')->not->toContain('contact.');
    expect($r->json('message'))->not->toContain('warn_days')->not->toContain('contact.warn');
});

it('stores no English internal text in status-change activities (B-06)', function () {
    $p = dm_prospect(['pipeline_status' => 'new']);

    $this->postJson("/api/prospects/{$p->id}/status", ['status' => 'contacted'])->assertOk();

    $a = ProspectActivity::where('prospect_id', $p->id)->where('type', 'status_change')->latest('id')->first();
    expect($a->body)->toBeNull()->and($a->from_status)->toBe('new')->and($a->to_status)->toBe('contacted');
});

it('records no status transition when contacting a prospect that is already contacted (B-06)', function () {
    $p = dm_prospect(['phone' => '081255550101', 'pipeline_status' => 'contacted', 'contacted_at' => now()->subDays(30), 'last_contacted_at' => now()->subDays(20)]);

    $this->postJson("/api/prospects/{$p->id}/contact", ['message' => 'Follow up'])->assertOk();

    $a = ProspectActivity::where('prospect_id', $p->id)->where('type', 'contacted')->latest('id')->first();
    expect($a->from_status)->toBeNull()->and($a->to_status)->toBeNull();
});

it('redacts keys and tokens in stored error text (security d)', function () {
    DataSource::create(['key' => 'google', 'name' => 'Google', 'is_active' => true, 'priority' => 1, 'api_key' => 'my-literal-secret-key']);

    $redacted = Redactor::redact('GET https://x.test/?key=AIzaSyA1234567890abcdefghijk&q=1 failed; X-Goog-Api-Key: abc123 token=zzz my-literal-secret-key Bearer abcdefgh12345');

    expect($redacted)->not->toContain('AIzaSy')->not->toContain('abc123')->not->toContain('zzz')->not->toContain('my-literal-secret-key')->not->toContain('abcdefgh12345')
        ->and($redacted)->toContain('q=1')->toContain('[redacted]');
    expect(Redactor::redact(null))->toBeNull();
});

it('redacts keys from the data source last_error written by sync', function () {
    $source = DataSource::create(['key' => 'google', 'name' => 'Google', 'is_active' => true, 'priority' => 1, 'api_key' => 'AIza-literal-0001']);
    $service = app(SyncService::class);
    $m = new ReflectionMethod($service, 'markSource');
    $m->invoke($service, app(GoogleProvider::class), 'error', 'Upstream rejected AIza-literal-0001 (key=AIza-literal-0001)');

    expect($source->fresh()->last_error)->not->toContain('AIza-literal-0001')->toContain('[redacted]');
});
