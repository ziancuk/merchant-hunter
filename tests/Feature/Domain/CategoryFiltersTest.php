<?php

use App\Models\Category;

beforeEach(fn () => dm_login());

function cf_payload(array $extra = []): array
{
    return $extra + ['key' => 'toko_buku', 'name' => 'Toko Buku', 'weight' => 60, 'whatsapp_template' => 'Halo {nama_bisnis}'];
}

it('rejects null osm_filters with 422 instead of a NOT NULL 500 (create and update)', function () {
    $this->postJson('/api/master/categories', cf_payload(['osm_filters' => null]))->assertStatus(422)->assertJsonValidationErrors('osm_filters');

    $category = dm_category(['osm_filters' => ['shop' => ['books']]]);
    $this->putJson("/api/master/categories/{$category->id}", cf_payload(['key' => $category->key, 'osm_filters' => null]))
        ->assertStatus(422)->assertJsonValidationErrors('osm_filters');
    expect($category->fresh()->osm_filters)->toBe(['shop' => ['books']]);
});

it('creates with an empty filter set when osm_filters is omitted and keeps filters on update', function () {
    $id = $this->postJson('/api/master/categories', cf_payload())->assertCreated()->assertJsonPath('osm_filters', [])->json('id');

    $this->putJson("/api/master/categories/{$id}", cf_payload(['osm_filters' => ['shop' => ['books']]]))->assertOk();
    $this->putJson("/api/master/categories/{$id}", cf_payload(['name' => 'Renamed']))->assertOk()->assertJsonPath('osm_filters', ['shop' => ['books']]);
    expect(Category::query()->find($id)->osm_filters)->toBe(['shop' => ['books']]);
});

it('validates osm_filters keys and values against the safe pattern', function () {
    $this->postJson('/api/master/categories', cf_payload(['osm_filters' => ['shop' => ['bo"oks']]]))->assertStatus(422);
    $this->postJson('/api/master/categories', cf_payload(['osm_filters' => ['shop' => ['a b']]]))->assertStatus(422);
    $this->postJson('/api/master/categories', cf_payload(['osm_filters' => ['sh"op' => ['books']]]))->assertStatus(422)->assertJsonValidationErrors('osm_filters');
    $this->postJson('/api/master/categories', cf_payload(['osm_filters' => ['shop' => 'books']]))->assertStatus(422);
    $this->postJson('/api/master/categories', cf_payload(['osm_filters' => ['shop' => ['books', 'stationery_1']]]))->assertCreated();
});
