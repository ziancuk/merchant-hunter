<?php

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Models\Province;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/** Shared builders for the Domain feature tests (factories run unguarded, so pipeline fields can be set). */
function dm_login(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    test()->actingAs($user);

    return $user;
}

function dm_province(array $a = []): Province
{
    return Province::factory()->create($a);
}

function dm_category(array $a = []): Category
{
    return Category::factory()->create($a + ['key' => 'cat_'.Str::lower(Str::random(8)), 'name' => 'Kafe', 'weight' => 80]);
}

function dm_outlet(array $a = []): Outlet
{
    $a['province_id'] ??= dm_province()->id;

    return Outlet::factory()->create($a);
}

function dm_prospect(array $a = []): Prospect
{
    $category = Category::query()->orderBy('id')->first() ?? dm_category();

    return Prospect::factory()->create($a + ['category_id' => $category->id]);
}

function dm_reset_whitespace_cache(): void
{
    Cache::flush();
}
