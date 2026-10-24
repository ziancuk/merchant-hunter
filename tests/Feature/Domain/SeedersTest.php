<?php

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Province;
use App\Services\Contact\MessageBuilder;
use App\Services\Geo\Haversine;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CategoryTemplateSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OutletSeeder;
use Database\Seeders\ProvinceSeeder;

it('seeds 190 simulated outlets, five per province, with sequential codes', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Outlet::count())->toBe(190)
        ->and(Outlet::where('is_simulated', true)->where('geocode_source', 'seed')->where('geocode_status', 'ok')->count())->toBe(190)
        ->and(Outlet::min('code'))->toBe('OUT-001')
        ->and(Outlet::max('code'))->toBe('OUT-190');

    Province::all()->each(fn (Province $p) => expect(Outlet::where('province_id', $p->id)->count())->toBe(5));
});

it('places every outlet within 6 km of its province capital in the capital city', function () {
    $this->seed(DatabaseSeeder::class);

    Outlet::with('province')->get()->each(function (Outlet $o) {
        $d = Haversine::distance($o->province->lat, $o->province->lng, $o->lat, $o->lng);
        expect($d)->toBeLessThan(6000)->and($o->city)->toBe($o->province->capital_city);
    });
});

it('spreads the five outlets of a province apart', function () {
    $this->seed(DatabaseSeeder::class);

    $points = Outlet::where('province_id', Province::where('code', 'JK')->value('id'))->get();
    foreach ($points as $a) {
        foreach ($points as $b) {
            if ($a->id < $b->id) {
                expect(Haversine::distance($a->lat, $a->lng, $b->lat, $b->lng))->toBeGreaterThan(1000);
            }
        }
    }
});

it('uses unique, non-empty fictional names', function () {
    $names = collect(OutletSeeder::rows())->pluck('name');

    expect($names)->toHaveCount(190)->and($names->unique())->toHaveCount(190)
        ->and($names->every(fn ($n) => strlen($n) > 5))->toBeTrue();
});

it('is deterministic and idempotent', function () {
    $this->seed(DatabaseSeeder::class);
    $first = Outlet::orderBy('code')->get(['code', 'name', 'lat', 'lng'])->toArray();
    $this->seed(OutletSeeder::class);
    $second = Outlet::orderBy('code')->get(['code', 'name', 'lat', 'lng'])->toArray();

    expect(Outlet::count())->toBe(190)->and($second)->toBe($first)
        ->and(OutletSeeder::rows())->toBe(OutletSeeder::rows());
});

it('seeds provinces itself when none exist', function () {
    $this->seed(OutletSeeder::class);

    expect(Province::count())->toBe(38)->and(Outlet::count())->toBe(190);
});

it('gives each category its own valid WhatsApp template with sender and opt-out', function () {
    $this->seed(DatabaseSeeder::class);

    $templates = Category::pluck('whatsapp_template', 'key');
    expect($templates->unique())->toHaveCount(12);
    foreach (CategoryTemplateSeeder::bodies() as $key => $body) {
        $tpl = $templates[$key];
        expect(MessageBuilder::isValidTemplate($tpl))->toBeTrue()
            ->and($tpl)->toContain('{nama_bisnis}')->toContain('{nama_pengirim}')->toContain('STOP');
    }
});

it('does not overwrite templates that were edited', function () {
    $this->seed(CategorySeeder::class);
    Category::where('key', 'cafe')->update(['whatsapp_template' => 'Kustom {nama_bisnis}']);
    $this->seed(CategoryTemplateSeeder::class);

    expect(Category::where('key', 'cafe')->value('whatsapp_template'))->toBe('Kustom {nama_bisnis}')
        ->and(Category::where('key', 'bakery')->value('whatsapp_template'))->toContain('STOP');
});

it('covers every seeded province row', function () {
    expect(count(ProvinceSeeder::rows()) * OutletSeeder::PER_PROVINCE)->toBe(190);
});
