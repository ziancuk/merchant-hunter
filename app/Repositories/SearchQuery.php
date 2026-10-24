<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\LifecycleStatus;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Models\Province;

final class SearchQuery
{
    public const LIMIT = 8;

    /** @return array{outlets: list<array<string, mixed>>, prospects: list<array<string, mixed>>, provinces: list<array<string, mixed>>} */
    public function search(string $term): array
    {
        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        $outlets = Outlet::query()->active()->located()
            ->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '\\'", [$like])->orWhereRaw("code LIKE ? ESCAPE '\\'", [$like]))
            ->orderBy('name')->limit(self::LIMIT)
            ->get(['id', 'name', 'city', 'lat', 'lng'])
            ->map(fn (Outlet $o) => ['id' => $o->id, 'name' => $o->name, 'city' => $o->city, 'lng' => $o->lng, 'lat' => $o->lat])
            ->all();

        $prospects = Prospect::query()
            ->join('categories', 'categories.id', '=', 'prospects.category_id')
            ->where('prospects.lifecycle_status', LifecycleStatus::Open->value)
            ->whereNotNull('prospects.lat')->whereNotNull('prospects.lng')
            ->whereRaw("prospects.name LIKE ? ESCAPE '\\'", [$like])
            ->orderByDesc('prospects.score')->orderBy('prospects.id')->limit(self::LIMIT)
            ->toBase()
            ->get(['prospects.id', 'prospects.name', 'categories.name as category', 'prospects.city', 'prospects.score', 'prospects.lng', 'prospects.lat'])
            ->map(fn ($r) => [
                'id' => (int) $r->id, 'name' => $r->name, 'category' => $r->category, 'city' => $r->city,
                'score' => (int) $r->score, 'lng' => (float) $r->lng, 'lat' => (float) $r->lat,
            ])->all();

        $provinces = Province::query()
            ->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '\\'", [$like])->orWhereRaw("capital_city LIKE ? ESCAPE '\\'", [$like]))
            ->orderBy('name')->limit(self::LIMIT)
            ->get(['id', 'name', 'lat', 'lng'])
            ->map(fn (Province $p) => ['id' => $p->id, 'name' => $p->name, 'lng' => $p->lng, 'lat' => $p->lat])
            ->all();

        return ['outlets' => $outlets, 'prospects' => $prospects, 'provinces' => $provinces];
    }
}
