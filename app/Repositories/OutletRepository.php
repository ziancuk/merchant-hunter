<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\GeocodeStatus;
use App\Http\Resources\OutletResource;
use App\Models\Outlet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class OutletRepository
{
    public const PER_PAGE = 25;

    /** @param array{search?: string|null, province_id?: int|string|null, geocode_status?: string|null} $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Outlet::query()
            ->with('province:id,name')
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->whereRaw("name LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("code LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("city LIKE ? ESCAPE '\\'", [$like]));
            })
            ->when(! empty($filters['province_id']), fn ($q) => $q->where('province_id', (int) $filters['province_id']))
            ->when(! empty($filters['geocode_status']), fn ($q) => $q->where('geocode_status', $filters['geocode_status']))
            ->orderBy('code')
            ->paginate(self::PER_PAGE);
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function paginated(array $filters): array
    {
        $page = $this->paginate($filters);

        return [
            'data' => OutletResource::collection($page->items())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'per_page' => $page->perPage(),
            ],
        ];
    }

    public function reviewCount(): int
    {
        return Outlet::query()->where('geocode_status', GeocodeStatus::NeedsReview->value)->count();
    }

    /** @return list<array{outlet: array<string, mixed>, candidates: list<array<string, mixed>>}> */
    public function reviewQueue(): array
    {
        return Outlet::query()
            ->with('province:id,name')
            ->where('geocode_status', GeocodeStatus::NeedsReview->value)
            ->orderBy('code')->limit(200)->get()
            ->map(fn (Outlet $o) => [
                'outlet' => OutletResource::make($o)->resolve(),
                'candidates' => array_values($o->geocode_candidates ?? []),
            ])->all();
    }
}
