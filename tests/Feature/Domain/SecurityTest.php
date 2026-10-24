<?php

use App\Models\DataSource;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/helpers.php';

/** @return list<LaravelRoute> */
function dm_app_routes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (LaravelRoute $r) => ! in_array($r->uri(), ['up', 'login', 'storage/{path}'], true) && ! str_starts_with($r->uri(), 'storage/'),
    ));
}

it('requires authentication on every application route (JSON gets 401)', function () {
    $routes = dm_app_routes();
    expect(count($routes))->toBeGreaterThan(40);

    foreach ($routes as $route) {
        $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $this->json($method, $uri)->assertUnauthorized();
        }
    }
});

it('redirects guests to /login for pages and exports', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with(['/', '/pipeline', '/dashboard', '/impact', '/master/data-sources', '/master/outlets', '/master/outlets/review', '/master/categories', '/scheduler', '/master/settings', '/prospects/export.csv', '/dashboard/export.csv']);

it('declares the auth middleware on every non-login route', function () {
    foreach (dm_app_routes() as $route) {
        expect(in_array('auth', $route->gatherMiddleware(), true))->toBeTrue($route->methods()[0].' '.$route->uri().' lacks auth');
    }
});

it('keeps CSRF protection in the web middleware group', function () {
    $this->get('/login'); // boots the HTTP kernel so the groups are registered
    $web = app('router')->getMiddlewareGroups()['web'] ?? [];

    expect($web)->toContain(ValidateCsrfToken::class);
});

it('answers validation failures on /api routes as {message, errors}', function () {
    dm_login();

    $r = $this->postJson('/api/master/categories', [])->assertStatus(422);

    expect($r->json())->toHaveKeys(['message', 'errors'])->and($r->json('errors'))->toBeArray()->and($r->json('message'))->toBeString();
    $this->getJson('/api/does-not-exist')->assertNotFound()->assertJsonStructure(['message']);
});

it('returns Indonesian validation messages', function () {
    dm_login();

    $r = $this->postJson('/api/master/categories', [])->assertStatus(422);

    expect($r->json('errors.name.0'))->toContain('wajib diisi');
});

it('ignores mass-assigned protected fields when creating an outlet', function () {
    dm_login();
    $prov = dm_province();

    $this->postJson('/api/master/outlets', [
        'code' => 'OUT-777', 'name' => 'Toko', 'city' => 'Kota', 'province_id' => $prov->id, 'lat' => -6.2, 'lng' => 106.8,
        'is_simulated' => true, 'last_synced_at' => now()->toDateTimeString(), 'baseline_done_at' => now()->toDateTimeString(), 'id' => 12345,
    ])->assertCreated();

    $o = Outlet::where('code', 'OUT-777')->firstOrFail();
    expect($o->is_simulated)->toBeFalse()->and($o->last_synced_at)->toBeNull()->and($o->baseline_done_at)->toBeNull()->and($o->id)->not->toBe(12345);
});

it('ignores protected fields on category updates', function () {
    dm_login();
    $c = dm_category(['key' => 'cafe']);

    $this->putJson("/api/master/categories/{$c->id}", ['key' => 'cafe', 'name' => 'Kafe', 'weight' => 70, 'whatsapp_template' => 'Halo {nama_bisnis}', 'id' => 999, 'sort_order' => 99, 'created_at' => '2000-01-01'])->assertOk();

    expect($c->fresh()->id)->toBe($c->id)->and($c->fresh()->sort_order)->not->toBe(99);
});

it('never leaks an API key in any response', function () {
    dm_login();
    $s = DataSource::create(['key' => 'google', 'name' => 'Google', 'is_active' => false, 'priority' => 5]);
    $this->putJson("/api/master/data-sources/{$s->id}", ['api_key' => 'AIzaTOPSECRET-5678'])->assertOk();

    $responses = [
        $this->getJson('/api/master/data-sources'),
        $this->get('/master/data-sources'),
        $this->putJson("/api/master/data-sources/{$s->id}", ['priority' => 2]),
        $this->getJson('/api/settings'),
        $this->get('/master/settings'),
        $this->get('/dashboard'),
        $this->get('/'),
    ];
    foreach ($responses as $response) {
        expect($response->getContent())->not->toContain('TOPSECRET');
    }
    expect(json_encode($s->fresh()->toArray()))->not->toContain('TOPSECRET');
});

it('escapes business names in Inertia page props (data, not markup)', function () {
    dm_login();
    config(['inertia.testing.ensure_pages_exist' => false]);
    $payload = '<img src=x onerror=alert(1)>';
    dm_prospect(['name' => $payload, 'lat' => -6.2, 'lng' => 106.8, 'pipeline_status' => 'new']);

    $html = $this->get('/pipeline')->getContent();

    // the Inertia page object is embedded as JSON in an attribute: the raw tag must not appear unescaped
    expect($html)->not->toContain($payload);
});

it('does not expose another user\'s password hash anywhere in shared props', function () {
    $u = dm_login();

    $content = $this->get('/pipeline')->getContent();

    expect($content)->not->toContain($u->password)->not->toContain('remember_token');
    expect(User::count())->toBe(1);
});

it('throttles the map endpoints at 300/min (middleware present)', function () {
    $route = Route::getRoutes()->match(request()->create('/api/map/prospects', 'GET'));

    expect($route->gatherMiddleware())->toContain('throttle:300,1,map');
});
