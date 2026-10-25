<?php

use App\Models\Outlet;
use App\Repositories\OutletRepository;
use App\Support\LikeEscape;

require_once __DIR__.'/helpers.php';

it('escapes the wildcards and the escape character itself', function () {
    expect(LikeEscape::escape('50%_off!'))->toBe('50!%!_off!!')
        ->and(LikeEscape::contains('a_b'))->toBe('%a!_b%')
        ->and(LikeEscape::clause('name'))->toBe("name LIKE ? ESCAPE '!'")
        ->and(LikeEscape::escape('\\'))->toBe('\\'); // backslash is an ordinary character now
});

it('matches %, _ and ! literally in the master outlet search', function () {
    $province = dm_province();
    foreach (['Toko 100% Murah', 'Toko 1000 Murah', 'Warung_Bu_Sri', 'Warung Bu Sri', 'Kopi!Kita', 'Kopi Kita', 'Back\\slash'] as $i => $name) {
        Outlet::factory()->create(['code' => 'T'.$i, 'name' => $name, 'city' => 'Bandung', 'province_id' => $province->id]);
    }
    $search = fn (string $term) => collect(app(OutletRepository::class)->paginate(['search' => $term])->items())->pluck('name')->all();

    expect($search('100%'))->toBe(['Toko 100% Murah'])
        ->and($search('%'))->toBe(['Toko 100% Murah'])
        ->and($search('_Bu_'))->toBe(['Warung_Bu_Sri'])
        ->and($search('_'))->toBe(['Warung_Bu_Sri'])
        ->and($search('!'))->toBe(['Kopi!Kita'])
        ->and($search('i!K'))->toBe(['Kopi!Kita'])
        ->and($search('\\'))->toBe(['Back\\slash']);
});

it('matches %, _ and ! literally in the global search', function () {
    dm_login();
    $province = dm_province();
    Outlet::factory()->create(['code' => 'A1', 'name' => 'Cabang 50% Off', 'province_id' => $province->id, 'lat' => -6.2, 'lng' => 106.8]);
    Outlet::factory()->create(['code' => 'A2', 'name' => 'Cabang 500 Off', 'province_id' => $province->id, 'lat' => -6.2, 'lng' => 106.8]);
    dm_prospect(['name' => 'Kopi!Kita', 'lat' => -6.2, 'lng' => 106.8]);
    dm_prospect(['name' => 'Kopi Kita', 'lat' => -6.2, 'lng' => 106.8]);
    dm_prospect(['name' => 'Kopi_Kita', 'lat' => -6.2, 'lng' => 106.8]);

    $this->getJson('/api/search?q=50%25')->assertJsonCount(1, 'outlets')->assertJsonPath('outlets.0.name', 'Cabang 50% Off');
    $this->getJson('/api/search?q=i!K')->assertJsonCount(1, 'prospects')->assertJsonPath('prospects.0.name', 'Kopi!Kita');
    $this->getJson('/api/search?q=i_K')->assertJsonCount(1, 'prospects')->assertJsonPath('prospects.0.name', 'Kopi_Kita');
});
