<?php

use App\Models\Outlet;
use App\Models\Province;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    Queue::fake();
    dm_login();
    Province::factory()->create(['name' => 'Bali']);
});

function oi_import($test, string $csv)
{
    return $test->postJson('/api/master/outlets/import', ['file' => UploadedFile::fake()->createWithContent('o.csv', $csv)]);
}

it('does not silently reactivate a deactivated outlet but still updates its data', function () {
    $outlet = Outlet::factory()->create(['code' => 'OUT-1', 'name' => 'Lama', 'city' => 'Denpasar', 'is_active' => false, 'province_id' => Province::query()->first()->id]);

    oi_import($this, "code,name,address,city,province,lat,lng\nOUT-1,Baru,,Denpasar,Bali,-8.6,115.2\n")->assertOk()->assertJsonPath('updated', 1);

    $outlet->refresh();
    expect($outlet->is_active)->toBeFalse()->and($outlet->name)->toBe('Baru');
});

it('creates new outlets as active', function () {
    oi_import($this, "code,name,address,city,province,lat,lng\nOUT-2,Toko,,Denpasar,Bali,-8.6,115.2\n")->assertOk()->assertJsonPath('created', 1);

    expect(Outlet::query()->where('code', 'OUT-2')->value('is_active'))->toBeTrue();
});

it('imports more rows than one chunk with one existing-code lookup per chunk and a repeated code updating one row', function () {
    $csv = "code,name,address,city,province,lat,lng\n";
    for ($i = 1; $i <= 450; $i++) {
        $csv .= "C{$i},Outlet {$i},,Denpasar,Bali,-8.6,115.2\n";
    }
    $csv .= "C1,Outlet satu lagi,,Denpasar,Bali,-8.6,115.2\n"; // repeated code in a later chunk -> update

    DB::flushQueryLog();
    DB::enableQueryLog();
    $r = oi_import($this, $csv)->assertOk();
    $lookups = collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with($q['query'], 'select * from "outlets" where "code" in'))->count();

    $r->assertJsonPath('created', 450)->assertJsonPath('updated', 1)->assertJsonPath('failed', 0);
    expect($lookups)->toBe(3)->and(Outlet::query()->count())->toBe(450)->and(Outlet::query()->where('code', 'C1')->value('name'))->toBe('Outlet satu lagi');
});

it('keeps importing later rows after a row fails validation', function () {
    $csv = "code,name,address,city,province,lat,lng\nA1,Ok,,Denpasar,Bali,-8.6,115.2\nA2,Bad,,Denpasar,Atlantis,,\nA3,Ok,,Denpasar,Bali,,\n";

    oi_import($this, $csv)->assertOk()->assertJsonPath('created', 2)->assertJsonPath('failed', 1)->assertJsonPath('queued', 1);
});
