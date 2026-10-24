<?php

use App\Models\Outlet;
use App\Models\Province;
use App\Models\SchedulerRun;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    Cache::flush();
    $this->user = dm_login(['name' => 'Ayu']);
});

// ---------------------------------------------------------------- pipeline API

it('returns kanban columns with totals and Card payloads', function () {
    $cat = dm_category(['name' => 'Kafe']);
    dm_prospect(['category_id' => $cat->id, 'name' => 'A', 'score' => 90, 'city' => 'Jakarta', 'phone' => '0812', 'handled_by' => $this->user->id, 'last_contacted_at' => now()->subDay()]);
    dm_prospect(['category_id' => $cat->id, 'name' => 'B', 'score' => 40]);
    dm_prospect(['category_id' => $cat->id, 'name' => 'C', 'pipeline_status' => 'contacted', 'score' => 60]);

    $r = $this->getJson('/api/pipeline/cards')->assertOk();

    $r->assertJsonStructure(['columns' => ['new' => ['total', 'cards'], 'contacted', 'demo', 'won', 'lost']])
        ->assertJsonPath('columns.new.total', 2)->assertJsonPath('columns.contacted.total', 1)->assertJsonPath('columns.won.total', 0)
        ->assertJsonPath('columns.new.cards.0.name', 'A')   // score desc
        ->assertJsonPath('columns.new.cards.0.category', 'Kafe')
        ->assertJsonPath('columns.new.cards.0.score_band', 'hot')
        ->assertJsonPath('columns.new.cards.0.phone_present', true)
        ->assertJsonPath('columns.new.cards.0.handled_by', 'Ayu')
        ->assertJsonPath('columns.new.cards.1.phone_present', false);
    expect($r->json('columns.new.cards.0'))->toHaveKeys(['id', 'name', 'category', 'city', 'score', 'score_band', 'phone_present', 'last_contacted_at', 'handled_by', 'badges', 'do_not_contact']);
});

it('paginates one kanban column with a cursor', function () {
    foreach (range(1, 5) as $i) {
        dm_prospect(['score' => 100 - $i]);
    }

    $page1 = $this->getJson('/api/pipeline/cards?status=new&per_status=2')->assertOk();
    expect($page1->json('columns'))->toHaveCount(1)->and($page1->json('columns.new.cards'))->toHaveCount(2)->and($page1->json('columns.new.total'))->toBe(5);

    $cursor = $page1->json('columns.new.next_cursor');
    $page2 = $this->getJson('/api/pipeline/cards?status=new&per_status=2&cursor='.$cursor)->assertOk();
    $page3 = $this->getJson('/api/pipeline/cards?status=new&per_status=2&cursor='.$page2->json('columns.new.next_cursor'))->assertOk();

    $scores = array_merge(
        array_column($page1->json('columns.new.cards'), 'score'),
        array_column($page2->json('columns.new.cards'), 'score'),
        array_column($page3->json('columns.new.cards'), 'score'),
    );
    expect($scores)->toBe([99, 98, 97, 96, 95])->and($page3->json('columns.new.next_cursor'))->toBeNull();
});

it('applies the shared filters to kanban and table', function () {
    $prov = dm_province();
    dm_prospect(['province_id' => $prov->id, 'score' => 80, 'name' => 'Match']);
    dm_prospect(['score' => 80, 'name' => 'Other province']);
    dm_prospect(['province_id' => $prov->id, 'score' => 10, 'name' => 'Low']);

    $q = '?province_id='.$prov->id.'&score_min=50';
    $this->getJson('/api/pipeline/cards'.$q)->assertJsonPath('columns.new.total', 1);
    $this->getJson('/api/pipeline/table'.$q)->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Match');
});

it('serves a sortable, paginated table with Laravel meta', function () {
    foreach (['Charlie' => 10, 'Alpha' => 50, 'Bravo' => 30] as $name => $score) {
        dm_prospect(['name' => $name, 'score' => $score, 'last_contacted_at' => $name === 'Alpha' ? null : now()->subDays($score)]);
    }

    $byScore = $this->getJson('/api/pipeline/table')->assertOk();
    expect(array_column($byScore->json('data'), 'name'))->toBe(['Alpha', 'Bravo', 'Charlie'])
        ->and($byScore->json('meta'))->toBe(['current_page' => 1, 'last_page' => 1, 'total' => 3, 'per_page' => 25]);

    expect(array_column($this->getJson('/api/pipeline/table?sort=name&dir=asc')->json('data'), 'name'))->toBe(['Alpha', 'Bravo', 'Charlie']);
    expect(array_column($this->getJson('/api/pipeline/table?sort=name&dir=desc')->json('data'), 'name'))->toBe(['Charlie', 'Bravo', 'Alpha']);
    // never-contacted last, most recent first when descending
    expect(array_column($this->getJson('/api/pipeline/table?sort=last_contacted_at&dir=desc')->json('data'), 'name'))->toBe(['Charlie', 'Bravo', 'Alpha']);

    $paged = $this->getJson('/api/pipeline/table?per_page=2&page=2')->assertOk();
    expect($paged->json('data'))->toHaveCount(1)->and($paged->json('meta.last_page'))->toBe(2);
});

it('validates pipeline query parameters', function () {
    $this->getJson('/api/pipeline/table?per_page=101')->assertStatus(422)->assertJsonValidationErrors('per_page');
    $this->getJson('/api/pipeline/table?sort=password')->assertStatus(422)->assertJsonValidationErrors('sort');
    $this->getJson('/api/pipeline/table?dir=sideways')->assertStatus(422);
    $this->getJson('/api/pipeline/cards?status=bogus')->assertStatus(422);
    $this->getJson('/api/pipeline/cards?per_status=0')->assertStatus(422);
});

it('does not run a query per card (eager loads category and handler)', function () {
    foreach (range(1, 30) as $i) {
        dm_prospect(['handled_by' => $this->user->id]);
    }

    DB::enableQueryLog();
    $this->getJson('/api/pipeline/table?per_page=30')->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBeLessThanOrEqual(6);
});

// ---------------------------------------------------------------- inertia pages

it('renders Map/Index with the contract props', function () {
    $prov = Province::factory()->create(['name' => 'DKI Jakarta', 'lat' => -6.2, 'lng' => 106.8]);
    dm_outlet(['province_id' => $prov->id, 'city' => 'Jakarta Pusat']);
    dm_outlet(['province_id' => $prov->id, 'city' => 'Jakarta Pusat']);
    $cat = dm_category(['key' => 'cafe', 'name' => 'Kafe']);
    dm_category(['key' => 'off', 'is_active' => false]);
    dm_prospect(['category_id' => $cat->id, 'score' => 90]);
    dm_prospect(['category_id' => $cat->id, 'score' => 40, 'first_seen_at' => now()->subDays(2), 'is_new_open' => true]);
    dm_prospect(['category_id' => $cat->id, 'score' => 80, 'is_baseline' => true, 'first_seen_at' => now()->subDays(2)]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->component('Map/Index')
        ->has('filter_options.provinces', 1, fn ($p) => $p->where('id', $prov->id)->where('name', 'DKI Jakarta'))
        ->where('filter_options.cities', ['Jakarta Pusat'])
        ->has('filter_options.categories', 1, fn ($c) => $c->where('key', 'cafe')->where('name', 'Kafe')->etc())
        ->where('filter_options.pipeline_statuses', ['new', 'contacted', 'demo', 'won', 'lost'])
        ->where('hud.outlets', 2)->where('hud.prospects_active', 3)->where('hud.new_7d', 2)->where('hud.hot', 2)
        ->has('provinces_index', 1, fn ($p) => $p->where('lat', -6.2)->where('lng', 106.8)->etc())
        ->has('rejection_reasons')
        ->where('contact_rules', ['warn_days' => 7, 'min_interval_days' => 14, 'max_overrides' => 2]));
});

it('renders Pipeline/Index with view, columns, options, reasons and rules', function () {
    dm_prospect();

    $this->get('/pipeline')->assertInertia(fn (Assert $page) => $page
        ->component('Pipeline/Index')->where('view', 'kanban')->where('columns.new.total', 1)
        ->has('filter_options')->has('rejection_reasons')->has('contact_rules'));
    $this->get('/pipeline?view=table')->assertInertia(fn (Assert $page) => $page->where('view', 'table'));
});

it('renders the master and scheduler pages with their props', function () {
    dm_outlet();
    SchedulerRun::create(['command' => 'hunter:sync', 'status' => 'success', 'started_at' => now()->subHours(3), 'finished_at' => now()->subHours(2)]);

    $this->get('/impact')->assertInertia(fn (Assert $p) => $p->component('Impact/Index')->has('assumptions')->has('defaults')->has('results.google_calls_per_month'));
    $this->get('/master/data-sources')->assertInertia(fn (Assert $p) => $p->component('Master/DataSources')->has('sources'));
    $this->get('/master/outlets')->assertInertia(fn (Assert $p) => $p->component('Master/Outlets')->has('outlets.data', 1)->has('outlets.meta')->has('filters')->where('review_queue_count', 0));
    $this->get('/master/outlets/review')->assertInertia(fn (Assert $p) => $p->component('Master/OutletReview')->where('queue', []));
    $this->get('/master/categories')->assertInertia(fn (Assert $p) => $p->component('Master/Categories')->has('categories'));
    $this->get('/scheduler')->assertInertia(fn (Assert $p) => $p->component('Scheduler/Runs')->has('runs.data', 1)->has('runs.meta')->where('health.status', 'success'));
    $this->get('/master/settings')->assertInertia(fn (Assert $p) => $p->component('Master/Settings')->has('settings.contact')->has('settings.digest')->has('settings.sync')->has('settings.scoring')->has('settings.enrichment'));
});

it('lists outlets filtered by search, province and geocode status', function () {
    $prov = dm_province();
    dm_outlet(['province_id' => $prov->id, 'name' => 'Toko Sinar', 'code' => 'OUT-001']);
    dm_outlet(['name' => 'Warung Tini', 'code' => 'OUT-002', 'geocode_status' => 'needs_review']);

    $this->get('/master/outlets?search=sinar')->assertInertia(fn (Assert $p) => $p->has('outlets.data', 1));
    $this->get('/master/outlets?province_id='.$prov->id)->assertInertia(fn (Assert $p) => $p->has('outlets.data', 1));
    $this->get('/master/outlets?geocode_status=needs_review')->assertInertia(fn (Assert $p) => $p->has('outlets.data', 1)->where('review_queue_count', 1));
});

it('shares auth, flash and app props on every page', function () {
    config(['hunter.ai.key' => 'x']);

    $this->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->where('auth.user.email', $this->user->email)->has('flash')->where('app.ai_enabled', true)->has('app.basemap')->has('app.env'));
});

// ---------------------------------------------------------------- dashboard

it('computes the dashboard KPIs, cumulative funnel and penetration', function () {
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(12, 0));
    $prov = Province::factory()->create(['name' => 'DKI Jakarta']);
    dm_outlet(['province_id' => $prov->id]);
    dm_outlet(['province_id' => $prov->id]);
    dm_outlet(['province_id' => $prov->id, 'is_active' => false]);

    // 7 found (1 closed); 4 contacted: 2 won (1 this month), 1 demo, 1 lost
    dm_prospect(['province_id' => $prov->id, 'first_seen_at' => now()->subDays(2)]);                                               // new, fresh
    dm_prospect(['province_id' => $prov->id, 'first_seen_at' => now()->subDays(2), 'is_baseline' => true]);                          // baseline (not counted in new_7d)
    dm_prospect(['province_id' => $prov->id, 'first_seen_at' => now()->subDays(40), 'contacted_at' => now()->subDays(20), 'demo_at' => now()->subDays(15), 'won_at' => now()->subDays(10), 'pipeline_status' => 'won']); // won in Oct? 10 days ago = Oct 14
    dm_prospect(['province_id' => $prov->id, 'first_seen_at' => now()->subDays(40), 'contacted_at' => now()->subDays(50), 'demo_at' => now()->subDays(45), 'won_at' => now()->subDays(40), 'pipeline_status' => 'won']); // won in Sep
    dm_prospect(['province_id' => $prov->id, 'first_seen_at' => now()->subDays(40), 'contacted_at' => now()->subDays(5), 'demo_at' => now()->subDays(3), 'pipeline_status' => 'demo']);
    dm_prospect(['province_id' => $prov->id, 'first_seen_at' => now()->subDays(40), 'contacted_at' => now()->subDays(5), 'lost_at' => now()->subDays(4), 'pipeline_status' => 'lost']);
    dm_prospect(['province_id' => $prov->id, 'first_seen_at' => now()->subDays(40), 'lifecycle_status' => 'closed']);

    $this->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->component('Dashboard/Index')
        ->where('kpis.outlets', 2)->where('kpis.prospects_active', 6)->where('kpis.new_7d', 1)
        ->where('kpis.deals_this_month', 1)->where('kpis.conversion_rate', 0.5)
        ->where('funnel', ['found' => 7, 'contacted' => 4, 'demo' => 3, 'won' => 2, 'lost' => 1])
        ->has('penetration')->has('top_categories.0', fn ($c) => $c->where('category', 'Kafe')->where('count', 6)->has('avg_score'))
        ->has('whitespace_top')->has('activity_by_user')->has('scheduler_health')->has('digest.new_open'));

    $row = collect($this->get('/dashboard')->inertiaProps('penetration'))->firstWhere('province', 'DKI Jakarta');
    expect($row)->toMatchArray(['outlets' => 2, 'prospects' => 6, 'won' => 2])->and($row['penetration'])->toBe(0.3333);
});

it('reports zeros instead of dividing by zero', function () {
    $this->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->where('kpis.conversion_rate', 0)->where('funnel.found', 0)->where('scheduler_health.last_run_at', null)->where('scheduler_health.failed_last_7d', 0));
});

it('summarises activity by user from the stage timestamps', function () {
    $other = User::factory()->create(['name' => 'Budi']);
    dm_prospect(['handled_by' => $this->user->id, 'contacted_at' => now(), 'demo_at' => now(), 'won_at' => now()]);
    dm_prospect(['handled_by' => $this->user->id, 'contacted_at' => now()]);
    dm_prospect(['handled_by' => $other->id, 'contacted_at' => now()]);

    $rows = collect($this->get('/dashboard')->inertiaProps('activity_by_user'))->keyBy('name');

    expect($rows['Ayu'])->toMatchArray(['contacted' => 2, 'demo' => 1, 'won' => 1])->and($rows['Budi'])->toMatchArray(['contacted' => 1, 'demo' => 0, 'won' => 0]);
});

it('reports scheduler health age and failures', function () {
    $this->travelTo(now());
    SchedulerRun::create(['command' => 'hunter:sync', 'status' => 'partial', 'started_at' => now()->subHours(7), 'finished_at' => now()->subHours(6)]);
    SchedulerRun::create(['command' => 'hunter:sync', 'status' => 'failed', 'started_at' => now()->subDays(2)]);
    SchedulerRun::create(['command' => 'hunter:sync', 'status' => 'failed', 'started_at' => now()->subDays(20)]);

    $h = $this->get('/dashboard')->inertiaProps('scheduler_health');

    expect($h['status'])->toBe('partial')->and($h['age_hours'])->toEqual(6)->and($h['failed_last_7d'])->toBe(1)->and($h['last_run_at'])->toMatch('/Z$/');
});

it('lists the top white space cells with their province', function () {
    $prov = Province::factory()->create(['name' => 'Jawa Barat']);
    Outlet::factory()->create(['province_id' => $prov->id, 'lat' => -6.5, 'lng' => 107.2]);
    for ($i = 0; $i < 16; $i++) {
        dm_prospect(['lat' => -6.2051, 'lng' => 106.8051]);
    }

    $cells = $this->get('/dashboard')->inertiaProps('whitespace_top');

    expect($cells)->toHaveCount(1)->and($cells[0])->toMatchArray(['cell_id' => '-6.21:106.80', 'prospects' => 16, 'province' => 'Jawa Barat'])
        ->and($cells[0]['nearest_outlet_m'])->toBeGreaterThan(500);
});

// ---------------------------------------------------------------- settings & impact

it('returns and updates settings with range validation', function () {
    $this->getJson('/api/settings')->assertOk()->assertExactJson([
        'contact' => ['warn_days' => 7, 'min_interval_days' => 14, 'max_overrides' => 2],
        'digest' => ['email_to' => null, 'hot_threshold' => 75, 'follow_up_days' => 7],
        'sync' => ['max_outlets_per_run' => 200],
        'scoring' => ['params' => []],
        'enrichment' => ['simulated' => false],
        'demo' => ['allow_simulated_whatsapp' => false],
    ]);

    $this->putJson('/api/settings', [
        'contact' => ['warn_days' => 3, 'min_interval_days' => 30, 'max_overrides' => 1],
        'digest' => ['email_to' => 'boss@example.test', 'hot_threshold' => 80, 'follow_up_days' => 5],
        'sync' => ['max_outlets_per_run' => 50],
        'scoring' => ['params' => ['density_cap' => 10]],
        'enrichment' => ['simulated' => true],
        'demo' => ['allow_simulated_whatsapp' => true],
    ])->assertOk()->assertJsonPath('contact.warn_days', 3)->assertJsonPath('scoring.params.density_cap', 10)->assertJsonPath('demo.allow_simulated_whatsapp', true);

    expect(Setting::read('contact.min_interval_days'))->toBe(30)->and(Setting::read('digest.email_to'))->toBe('boss@example.test');
    $this->getJson('/api/settings')->assertJsonPath('sync.max_outlets_per_run', 50);
});

it('supports partial settings updates', function () {
    $this->putJson('/api/settings', ['contact' => ['warn_days' => 2]])->assertOk()
        ->assertJsonPath('contact.warn_days', 2)->assertJsonPath('contact.min_interval_days', 14);
});

it('rejects out-of-range settings', function (array $body, string $field) {
    $this->putJson('/api/settings', $body)->assertStatus(422)->assertJsonValidationErrors($field);
})->with([
    'warn_days' => [['contact' => ['warn_days' => -1]], 'contact.warn_days'],
    'min_interval' => [['contact' => ['min_interval_days' => 1000]], 'contact.min_interval_days'],
    'max_overrides' => [['contact' => ['max_overrides' => 21]], 'contact.max_overrides'],
    'hot_threshold' => [['digest' => ['hot_threshold' => 0]], 'digest.hot_threshold'],
    'email' => [['digest' => ['email_to' => 'not-an-email']], 'digest.email_to'],
    'max_outlets' => [['sync' => ['max_outlets_per_run' => 5000]], 'sync.max_outlets_per_run'],
    'bool' => [['demo' => ['allow_simulated_whatsapp' => 'maybe']], 'demo.allow_simulated_whatsapp'],
]);

it('applies stored scoring params, contact rules and demo flag through the settings service', function () {
    $this->putJson('/api/settings', ['contact' => ['warn_days' => 30]])->assertOk();
    $p = dm_prospect(['phone' => '081255550101', 'last_contacted_at' => now()->subDays(20), 'last_contacted_by' => $this->user->id]);

    $this->postJson("/api/prospects/{$p->id}/contact-check")->assertJsonPath('warning.days_ago', 20);
});

it('saves impact assumptions and returns results', function () {
    $r = $this->putJson('/api/settings/impact', ['outlets' => 100, 'google_syncs_per_month' => 10, 'google_groups_per_outlet' => 10, 'avg_pages' => 1, 'details_ratio' => 0, 'cost_per_1000_search' => 30, 'usd_idr' => 16000])->assertOk();

    $r->assertJsonPath('assumptions.outlets', 100)->assertJsonPath('results.google_calls_per_month', 10000)
        ->assertJsonPath('results.google_cost_usd', 300)->assertJsonPath('results.google_cost_idr', 4800000)->assertJsonPath('results.osm_cost_idr', 0)
        ->assertJsonStructure(['results' => ['google_calls_per_month', 'google_cost_usd', 'google_cost_idr', 'osm_cost_idr', 'prospects_found', 'expected_deals', 'expected_revenue_idr', 'hours_saved_per_month']]);

    $this->get('/impact')->assertInertia(fn (Assert $p) => $p->where('assumptions.outlets', 100)->where('defaults.outlets', 190)->where('results.google_calls_per_month', 10000));
});

it('validates impact assumptions and ignores unknown keys', function () {
    $this->putJson('/api/settings/impact', ['usd_idr' => 0])->assertStatus(422)->assertJsonValidationErrors('usd_idr');
    $this->putJson('/api/settings/impact', ['conv_demo_to_won' => 1.5])->assertStatus(422);
    $this->putJson('/api/settings/impact', ['outlets' => 12, 'evil' => 'x'])->assertOk()->assertJsonMissingPath('assumptions.evil');
});
