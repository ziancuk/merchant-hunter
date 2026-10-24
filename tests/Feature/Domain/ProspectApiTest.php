<?php

use App\Models\GeocodeQueueItem;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\RejectionReason;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->user = dm_login(['name' => 'Ayu']);
});

function reason(string $key = 'tidak_berminat'): RejectionReason
{
    return RejectionReason::firstOrCreate(['key' => $key], ['label' => 'Tidak berminat', 'sort_order' => 1]);
}

it('returns ProspectDetail with every contract field', function () {
    $outlet = dm_outlet(['name' => 'Toko Sinar Jaya']);
    $cat = dm_category(['key' => 'cafe', 'name' => 'Kafe']);
    $p = dm_prospect([
        'name' => 'Kopi Senja', 'category_id' => $cat->id, 'nearest_outlet_id' => $outlet->id, 'nearest_outlet_distance_m' => 640,
        'phone' => '0812-5555-0101', 'phone_simulated' => false, 'website' => 'https://kopisenja.test', 'rating' => 4.6, 'review_count' => 312,
        'score' => 82, 'score_breakdown' => [['key' => 'category', 'label' => 'x', 'value' => 0.8, 'points' => 20, 'max' => 25, 'reason' => 'r']],
        'address' => 'Jl. Melati No. 4', 'address_source' => 'reverse_geocode', 'neighbor_count_500m' => 2, 'area_density' => 18,
        'is_new_open' => true,
    ]);
    ProspectActivity::create(['prospect_id' => $p->id, 'type' => 'created', 'body' => 'Ditemukan oleh sync']);
    ProspectActivity::create(['prospect_id' => $p->id, 'user_id' => $this->user->id, 'type' => 'note', 'body' => 'Catatan pertama']);

    $r = $this->getJson("/api/prospects/{$p->id}")->assertOk();

    $r->assertJsonStructure([
        'id', 'name', 'category' => ['id', 'key', 'name'], 'address', 'address_source', 'lat', 'lng', 'phone', 'phone_simulated',
        'whatsapp_url', 'website', 'rating', 'rating_simulated', 'review_count', 'reviews_simulated', 'data_updated_at', 'first_seen_at',
        'score', 'score_band', 'score_breakdown', 'nearest_outlet' => ['id', 'name', 'distance_m'], 'neighbor_count_500m', 'area_density',
        'pipeline_status', 'rejection_reason', 'handled_by', 'last_contacted_at', 'last_contacted_by', 'contact_count', 'do_not_contact',
        'badges', 'lifecycle_status', 'latest_note', 'activities' => [['id', 'type', 'user', 'body', 'created_at']], 'contact_warning',
    ]);
    $r->assertJsonPath('name', 'Kopi Senja')->assertJsonPath('score_band', 'hot')
        ->assertJsonPath('whatsapp_url', 'https://wa.me/6281255550101')
        ->assertJsonPath('nearest_outlet.distance_m', 640)
        ->assertJsonPath('latest_note', 'Catatan pertama')
        ->assertJsonPath('activities.0.user.name', 'Ayu')
        ->assertJsonPath('badges', ['new_open', 'simulated'])
        ->assertJsonPath('address_source', 'reverse_geocode');
    expect($r->json('data_updated_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
});

it('returns 404 JSON for an unknown prospect', function () {
    $this->getJson('/api/prospects/99999')->assertNotFound()->assertJsonStructure(['message']);
});

it('drops non-http websites (javascript:, data:) from the detail', function (string $url, ?string $expected) {
    $p = dm_prospect(['website' => $url]);

    expect($this->getJson("/api/prospects/{$p->id}")->json('website'))->toBe($expected);
})->with([
    ['javascript:alert(1)', null],
    ['JavaScript:alert(1)', null],
    ['data:text/html,<script>', null],
    ['ftp://example.com', null],
    ['//evil.test', null],
    ['http://ok.test/path', 'http://ok.test/path'],
    ['https://ok.test', 'https://ok.test'],
]);

it('returns an XSS payload business name verbatim as data', function () {
    $name = '"><img src=x onerror=alert(1)>';
    $p = dm_prospect(['name' => $name]);

    $r = $this->getJson("/api/prospects/{$p->id}")->assertOk();

    expect($r->json('name'))->toBe($name)->and($r->headers->get('Content-Type'))->toContain('application/json');
});

it('exposes whatsapp_url only for valid, non-simulated, contactable mobile numbers', function () {
    $ok = dm_prospect(['phone' => '081255550101']);
    $landline = dm_prospect(['phone' => '021-5551234']);
    $none = dm_prospect(['phone' => null]);
    $optOut = dm_prospect(['phone' => '081255550102', 'do_not_contact' => true]);
    $simulated = dm_prospect(['phone' => '081255550103', 'phone_simulated' => true]);

    $url = fn (Prospect $p) => $this->getJson("/api/prospects/{$p->id}")->json('whatsapp_url');

    expect($url($ok))->toBe('https://wa.me/6281255550101')
        ->and($url($landline))->toBeNull()
        ->and($url($none))->toBeNull()
        ->and($url($optOut))->toBeNull()
        ->and($url($simulated))->toBeNull();

    Setting::write('demo.allow_simulated_whatsapp', true);
    expect($url($simulated))->toBe('https://wa.me/6281255550103');
});

it('bumps a pending geocode queue priority when the address is missing, with a DB write only', function () {
    Http::fake();
    $p = dm_prospect(['address' => null]);
    GeocodeQueueItem::create(['prospect_id' => $p->id, 'priority' => 10, 'status' => 'pending', 'available_at' => now()]);

    $this->getJson("/api/prospects/{$p->id}")->assertOk();

    expect(GeocodeQueueItem::where('prospect_id', $p->id)->value('priority'))->toBe(100);
    Http::assertNothingSent();

    $q = dm_prospect(['address' => 'Jl. A']);
    GeocodeQueueItem::create(['prospect_id' => $q->id, 'priority' => 10, 'status' => 'pending', 'available_at' => now()]);
    $this->getJson("/api/prospects/{$q->id}")->assertOk();
    expect(GeocodeQueueItem::where('prospect_id', $q->id)->value('priority'))->toBe(10);
});

// ---------------------------------------------------------------- contact-check

it('builds the contact-check payload with the category template and sender', function () {
    $cat = dm_category(['key' => 'cafe', 'name' => 'Kafe', 'whatsapp_template' => 'Halo {nama_bisnis} dari {nama_pengirim} ({kategori})']);
    $p = dm_prospect(['name' => 'Kopi Senja', 'category_id' => $cat->id, 'phone' => '0812 5555 0101']);

    $this->postJson("/api/prospects/{$p->id}/contact-check")->assertOk()->assertExactJson([
        'allowed' => true, 'blocked_reason' => null, 'warning' => null, 'min_interval_violation' => false,
        'phone_normalized' => '6281255550101', 'message' => 'Halo Kopi Senja dari Ayu (Kafe)',
        'template_category' => 'cafe', 'override_count' => 0, 'max_overrides' => 2,
    ]);
});

it('blocks contact-check for opt-out, closed, missing, landline and simulated numbers', function (array $attrs, string $reason) {
    $p = dm_prospect($attrs + ['phone' => '081255550101']);

    $this->postJson("/api/prospects/{$p->id}/contact-check")->assertOk()
        ->assertJsonPath('allowed', false)->assertJsonPath('blocked_reason', $reason)->assertJsonPath('phone_normalized', null);
})->with([
    'opt-out' => [['do_not_contact' => true], 'do_not_contact'],
    'closed' => [['lifecycle_status' => 'closed'], 'closed'],
    'no phone' => [['phone' => null], 'no_phone'],
    'landline' => [['phone' => '021-5551234'], 'invalid_phone'],
    'simulated' => [['phone_simulated' => true], 'invalid_phone'],
]);

it('allows simulated numbers only in demo mode', function () {
    $p = dm_prospect(['phone' => '081255550101', 'phone_simulated' => true]);
    Setting::write('demo.allow_simulated_whatsapp', true);

    $this->postJson("/api/prospects/{$p->id}/contact-check")->assertJsonPath('allowed', true)->assertJsonPath('phone_normalized', '6281255550101');
});

it('warns about a recent contact by another account', function () {
    $other = User::factory()->create(['name' => 'Budi']);
    $p = dm_prospect(['phone' => '081255550101', 'last_contacted_at' => now()->subDays(2), 'last_contacted_by' => $other->id, 'contact_count' => 1]);

    $r = $this->postJson("/api/prospects/{$p->id}/contact-check")->assertOk();

    $r->assertJsonPath('warning.type', 'recent_contact')->assertJsonPath('warning.by', 'Budi')->assertJsonPath('warning.days_ago', 2)
        ->assertJsonPath('min_interval_violation', true);
    expect($r->json('warning.at'))->toMatch('/Z$/');
    expect($this->getJson("/api/prospects/{$p->id}")->json('contact_warning.by'))->toBe('Budi');
});

it('flags the minimum interval but not the warning when the contact is 10 days old', function () {
    $p = dm_prospect(['phone' => '081255550101', 'last_contacted_at' => now()->subDays(10), 'last_contacted_by' => $this->user->id]);

    $this->postJson("/api/prospects/{$p->id}/contact-check")->assertJsonPath('warning', null)->assertJsonPath('min_interval_violation', true);
});

it('uses the configurable contact rules', function () {
    Setting::write('contact.warn_days', 30);
    Setting::write('contact.min_interval_days', 30);
    Setting::write('contact.max_overrides', 5);
    $p = dm_prospect(['phone' => '081255550101', 'last_contacted_at' => now()->subDays(20), 'last_contacted_by' => $this->user->id]);

    $this->postJson("/api/prospects/{$p->id}/contact-check")
        ->assertJsonPath('warning.days_ago', 20)->assertJsonPath('min_interval_violation', true)->assertJsonPath('max_overrides', 5);
});

// ---------------------------------------------------------------- contact

it('records a contact: activity, counters, handler and new -> contacted', function () {
    $p = dm_prospect(['phone' => '081255550101']);

    $r = $this->postJson("/api/prospects/{$p->id}/contact", ['message' => 'Halo Kopi Senja'])->assertOk();

    $r->assertJsonPath('prospect.pipeline_status', 'contacted')->assertJsonPath('prospect.contact_count', 1)
        ->assertJsonPath('prospect.handled_by.name', 'Ayu')->assertJsonPath('prospect.last_contacted_by.name', 'Ayu');
    $p->refresh();
    expect($p->contacted_at)->not->toBeNull()->and($p->last_contacted_at)->not->toBeNull()->and($p->handled_by)->toBe($this->user->id);
    $a = ProspectActivity::where('prospect_id', $p->id)->where('type', 'contacted')->first();
    expect($a->meta['message'])->toBe('Halo Kopi Senja')->and($a->meta['phone'])->toBe('6281255550101')->and($a->user_id)->toBe($this->user->id)
        ->and($a->from_status)->toBe('new')->and($a->to_status)->toBe('contacted');
});

it('does not regress the status when contacting a prospect that is already in demo', function () {
    $p = dm_prospect(['phone' => '081255550101', 'pipeline_status' => 'demo', 'contacted_at' => now()->subDays(30), 'last_contacted_at' => now()->subDays(20)]);

    $this->postJson("/api/prospects/{$p->id}/contact", ['message' => 'Follow up'])->assertOk()->assertJsonPath('prospect.pipeline_status', 'demo');
});

it('rejects contact for opt-out, no phone, landline and closed prospects with 422', function (array $attrs, string $code) {
    $p = dm_prospect($attrs + ['phone' => '081255550101']);

    $this->postJson("/api/prospects/{$p->id}/contact", ['message' => 'x'])->assertStatus(422)
        ->assertJsonPath('code', $code)->assertJsonStructure(['message', 'errors']);
    expect(ProspectActivity::where('prospect_id', $p->id)->count())->toBe(0)->and($p->fresh()->contact_count)->toBe(0);
})->with([
    'opt-out' => [['do_not_contact' => true], 'do_not_contact'],
    'no phone' => [['phone' => null], 'no_phone'],
    'landline' => [['phone' => '021-5551234'], 'invalid_phone'],
    'closed' => [['lifecycle_status' => 'closed'], 'closed'],
]);

it('validates the contact body', function () {
    $p = dm_prospect(['phone' => '081255550101']);

    $this->postJson("/api/prospects/{$p->id}/contact", [])->assertStatus(422)->assertJsonValidationErrors('message');
    $this->postJson("/api/prospects/{$p->id}/contact", ['message' => str_repeat('a', 1001)])->assertStatus(422);
});

it('requires an override reason inside the minimum interval and records it', function () {
    $p = dm_prospect(['phone' => '081255550101', 'last_contacted_at' => now()->subDays(3), 'last_contacted_by' => $this->user->id, 'contact_count' => 1, 'pipeline_status' => 'contacted']);

    $this->postJson("/api/prospects/{$p->id}/contact", ['message' => 'x'])->assertStatus(422)
        ->assertJsonPath('code', 'min_interval')->assertJsonValidationErrors('override_reason');
    expect($p->fresh()->contact_count)->toBe(1);

    $this->postJson("/api/prospects/{$p->id}/contact", ['message' => 'x', 'override_reason' => 'Pelanggan minta dihubungi'])->assertOk()
        ->assertJsonPath('prospect.contact_count', 2);
    $a = ProspectActivity::where('prospect_id', $p->id)->where('type', 'contacted')->latest('id')->first();
    expect($a->meta['override'])->toBeTrue()->and($a->meta['override_reason'])->toBe('Pelanggan minta dihubungi');
});

it('allows at most 2 overrides per prospect', function () {
    $p = dm_prospect(['phone' => '081255550101', 'last_contacted_at' => now()->subDay(), 'last_contacted_by' => $this->user->id, 'pipeline_status' => 'contacted']);

    foreach ([1, 2] as $i) {
        $this->postJson("/api/prospects/{$p->id}/contact", ['message' => 'x', 'override_reason' => "alasan {$i}"])->assertOk();
    }
    $this->postJson("/api/prospects/{$p->id}/contact-check")->assertJsonPath('override_count', 2)->assertJsonPath('max_overrides', 2);

    $this->postJson("/api/prospects/{$p->id}/contact", ['message' => 'x', 'override_reason' => 'ketiga'])->assertStatus(422)
        ->assertJsonPath('code', 'max_overrides');
    expect($p->fresh()->contact_count)->toBe(2);
});

it('does not require an override outside the minimum interval', function () {
    $p = dm_prospect(['phone' => '081255550101', 'last_contacted_at' => now()->subDays(20), 'last_contacted_by' => $this->user->id, 'pipeline_status' => 'contacted']);

    $this->postJson("/api/prospects/{$p->id}/contact", ['message' => 'x'])->assertOk();
    expect(ProspectActivity::where('prospect_id', $p->id)->first()->meta)->not->toHaveKey('override');
});

// ---------------------------------------------------------------- status

it('changes status, records the activity and stage timestamps only once', function () {
    $p = dm_prospect();

    $this->postJson("/api/prospects/{$p->id}/status", ['status' => 'demo'])->assertOk()
        ->assertJsonPath('prospect.pipeline_status', 'demo')->assertJsonPath('prospect.handled_by.id', $this->user->id);
    $first = $p->fresh();
    expect($first->demo_at)->not->toBeNull()->and($first->contacted_at)->not->toBeNull()->and($first->status_changed_at)->not->toBeNull();

    $this->travel(2)->days();
    $this->postJson("/api/prospects/{$p->id}/status", ['status' => 'contacted'])->assertOk();
    $this->postJson("/api/prospects/{$p->id}/status", ['status' => 'demo'])->assertOk();
    $again = $p->fresh();

    expect($again->demo_at->equalTo($first->demo_at))->toBeTrue()->and($again->contacted_at->equalTo($first->contacted_at))->toBeTrue();
    $a = ProspectActivity::where('prospect_id', $p->id)->where('type', 'status_change')->orderBy('id')->first();
    expect($a->from_status)->toBe('new')->and($a->to_status)->toBe('demo')->and(ProspectActivity::where('prospect_id', $p->id)->count())->toBe(3);
});

it('requires a rejection reason when the status is lost and logs a rejected activity', function () {
    $p = dm_prospect();
    $reason = reason();

    $this->postJson("/api/prospects/{$p->id}/status", ['status' => 'lost'])->assertStatus(422)->assertJsonValidationErrors('rejection_reason_id');
    $this->postJson("/api/prospects/{$p->id}/status", ['status' => 'lost', 'rejection_reason_id' => 99999])->assertStatus(422);
    expect($p->fresh()->pipeline_status->value)->toBe('new');

    $this->postJson("/api/prospects/{$p->id}/status", ['status' => 'lost', 'rejection_reason_id' => $reason->id])->assertOk()
        ->assertJsonPath('prospect.pipeline_status', 'lost')->assertJsonPath('prospect.rejection_reason.key', 'tidak_berminat');
    expect($p->fresh()->lost_at)->not->toBeNull()
        ->and(ProspectActivity::where('prospect_id', $p->id)->where('type', 'rejected')->count())->toBe(1);

    $this->postJson("/api/prospects/{$p->id}/status", ['status' => 'contacted'])->assertOk()->assertJsonPath('prospect.rejection_reason', null);
});

it('rejects an invalid status', function () {
    $p = dm_prospect();

    $this->postJson("/api/prospects/{$p->id}/status", ['status' => 'bogus'])->assertStatus(422)->assertJsonValidationErrors('status');
    $this->postJson("/api/prospects/{$p->id}/status", [])->assertStatus(422);
});

it('ignores mass-assigned protected fields in request bodies', function () {
    $p = dm_prospect(['phone' => '081255550101']);

    $this->postJson("/api/prospects/{$p->id}/status", ['status' => 'contacted', 'score' => 1, 'do_not_contact' => true, 'handled_by' => 999, 'name' => 'Hacked', 'contact_count' => 50])->assertOk();

    $p->refresh();
    expect($p->score)->not->toBe(1)->and($p->do_not_contact)->toBeFalse()->and($p->handled_by)->toBe($this->user->id)
        ->and($p->name)->not->toBe('Hacked')->and($p->contact_count)->toBe(0);
});

// ---------------------------------------------------------------- notes & opt-out

it('adds notes as activities', function () {
    $p = dm_prospect();

    $this->postJson("/api/prospects/{$p->id}/notes", ['body' => 'Pemilik minta dihubungi sore'])->assertCreated()
        ->assertJsonPath('activity.type', 'note')->assertJsonPath('activity.body', 'Pemilik minta dihubungi sore')->assertJsonPath('activity.user.name', 'Ayu');

    $this->postJson("/api/prospects/{$p->id}/notes", ['body' => ''])->assertStatus(422);
    $this->postJson("/api/prospects/{$p->id}/notes", ['body' => str_repeat('x', 2001)])->assertStatus(422);
    expect($this->getJson("/api/prospects/{$p->id}")->json('latest_note'))->toBe('Pemilik minta dihubungi sore');
});

it('sets and clears opt-out with activities', function () {
    $p = dm_prospect(['phone' => '081255550101']);

    $this->postJson("/api/prospects/{$p->id}/opt-out")->assertOk()
        ->assertJsonPath('prospect.do_not_contact', true)->assertJsonPath('prospect.whatsapp_url', null)->assertJsonPath('prospect.badges.1', 'opt_out');
    expect($p->fresh()->do_not_contact_by)->toBe($this->user->id)->and($p->fresh()->do_not_contact_at)->not->toBeNull();

    $this->deleteJson("/api/prospects/{$p->id}/opt-out")->assertOk()->assertJsonPath('prospect.do_not_contact', false);
    expect(ProspectActivity::where('prospect_id', $p->id)->pluck('type')->map->value->all())->toBe(['opt_out', 'opt_in'])
        ->and($p->fresh()->do_not_contact_at)->toBeNull();

    // idempotent: repeating does not duplicate the activity
    $this->deleteJson("/api/prospects/{$p->id}/opt-out")->assertOk();
    expect(ProspectActivity::where('prospect_id', $p->id)->count())->toBe(2);
});

// ---------------------------------------------------------------- bulk

it('bulk-updates status and reports skipped ids', function () {
    $a = dm_prospect();
    $b = dm_prospect(['pipeline_status' => 'contacted']);
    $c = dm_prospect();

    $r = $this->postJson('/api/prospects/bulk', ['ids' => [$a->id, $b->id, $c->id, 99999], 'action' => 'status', 'status' => 'contacted'])->assertOk();

    $r->assertJsonPath('updated', 2);
    expect($r->json('skipped'))->toBe([['id' => $b->id, 'reason' => 'same_status'], ['id' => 99999, 'reason' => 'not_found']])
        ->and($a->fresh()->pipeline_status->value)->toBe('contacted')->and($a->fresh()->handled_by)->toBe($this->user->id);
});

it('bulk opt-out skips already opted-out prospects', function () {
    $a = dm_prospect();
    $b = dm_prospect(['do_not_contact' => true]);

    $this->postJson('/api/prospects/bulk', ['ids' => [$a->id, $b->id], 'action' => 'opt_out'])->assertOk()
        ->assertJsonPath('updated', 1)->assertJsonPath('skipped.0.reason', 'already_opted_out');
    expect($a->fresh()->do_not_contact)->toBeTrue();
});

it('bulk lost requires a rejection reason', function () {
    $a = dm_prospect();

    $this->postJson('/api/prospects/bulk', ['ids' => [$a->id], 'action' => 'status', 'status' => 'lost'])->assertStatus(422)->assertJsonValidationErrors('rejection_reason_id');
    $this->postJson('/api/prospects/bulk', ['ids' => [$a->id], 'action' => 'status', 'status' => 'lost', 'rejection_reason_id' => reason()->id])->assertOk()->assertJsonPath('updated', 1);
});

it('validates bulk input: 1..200 ids, known action', function () {
    $this->postJson('/api/prospects/bulk', ['ids' => [], 'action' => 'status', 'status' => 'won'])->assertStatus(422)->assertJsonValidationErrors('ids');
    $this->postJson('/api/prospects/bulk', ['ids' => range(1, 201), 'action' => 'opt_out'])->assertStatus(422)->assertJsonValidationErrors('ids');
    $this->postJson('/api/prospects/bulk', ['ids' => [1], 'action' => 'delete'])->assertStatus(422)->assertJsonValidationErrors('action');
    $this->postJson('/api/prospects/bulk', ['ids' => [1], 'action' => 'status'])->assertStatus(422)->assertJsonValidationErrors('status');
    $this->postJson('/api/prospects/bulk', ['ids' => range(1, 200), 'action' => 'opt_out'])->assertOk()->assertJsonPath('updated', 0);
});

// ---------------------------------------------------------------- AI

it('returns 404 for the AI endpoint when no key is configured', function () {
    config(['hunter.ai.key' => null]);
    $p = dm_prospect();

    $this->postJson("/api/prospects/{$p->id}/message-ai")->assertNotFound()->assertJsonStructure(['message']);
});

it('drafts a message through the Anthropic Messages API', function () {
    config(['hunter.ai.key' => 'sk-test-key', 'hunter.ai.model' => 'claude-test']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Halo Kopi Senja, saya Ayu. Balas STOP.']]])]);
    $p = dm_prospect(['name' => 'Kopi Senja']);

    $this->postJson("/api/prospects/{$p->id}/message-ai", ['tone' => 'formal'])->assertOk()->assertExactJson(['message' => 'Halo Kopi Senja, saya Ayu. Balas STOP.']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->header('x-api-key')[0] === 'sk-test-key'
            && $request['model'] === 'claude-test'
            && str_contains($request['messages'][0]['content'], 'Kopi Senja')
            && str_contains($request['messages'][0]['content'], 'formal');
    });
});

it('fails gracefully when the AI service errors, without leaking the key', function () {
    config(['hunter.ai.key' => 'sk-secret-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'overloaded'], 529)]);
    $p = dm_prospect();

    $r = $this->postJson("/api/prospects/{$p->id}/message-ai")->assertStatus(502)->assertJsonStructure(['message', 'errors', 'code']);
    expect($r->getContent())->not->toContain('sk-secret-key');
});

it('rate-limits the AI endpoint to 10 per minute', function () {
    config(['hunter.ai.key' => 'sk-test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['text' => 'ok']]])]);
    $p = dm_prospect();

    foreach (range(1, 10) as $i) {
        $this->postJson("/api/prospects/{$p->id}/message-ai")->assertOk();
    }
    $this->postJson("/api/prospects/{$p->id}/message-ai")->assertStatus(429);
});

it('rejects an unknown AI tone', function () {
    config(['hunter.ai.key' => 'sk-test-key']);
    $p = dm_prospect();

    $this->postJson("/api/prospects/{$p->id}/message-ai", ['tone' => 'kasar'])->assertStatus(422)->assertJsonValidationErrors('tone');
});

it('leaves nothing behind when a status change fails validation', function () {
    $p = dm_prospect(['phone' => '081255550101']);
    $this->postJson("/api/prospects/{$p->id}/status", ['status' => 'lost', 'rejection_reason_id' => 424242])->assertStatus(422);

    expect($p->fresh()->pipeline_status->value)->toBe('new')->and(ProspectActivity::count())->toBe(0);
});

it('names the nearest outlet in the detail payload', function () {
    $outlet = Outlet::factory()->create(['province_id' => dm_province()->id, 'name' => 'Warung Bu Tini']);
    $p = dm_prospect(['nearest_outlet_id' => $outlet->id, 'nearest_outlet_distance_m' => 120]);

    $this->getJson("/api/prospects/{$p->id}")->assertJsonPath('nearest_outlet.name', 'Warung Bu Tini');
});
