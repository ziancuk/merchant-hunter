<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\DataSourceResource;
use App\Http\Resources\SchedulerRunResource;
use App\Models\Category;
use App\Models\DataSource;
use App\Models\Outlet;
use App\Models\Province;
use App\Models\RejectionReason;
use App\Models\SchedulerRun;
use App\Services\Impact\ImpactCalculator;
use App\Services\Settings\SettingsService;

/** Props shared by several Inertia pages (CONTRACTS §1). */
final class PageProps
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly DashboardQuery $dashboard,
        private readonly OutletRepository $outlets,
    ) {}

    /** @return array<string, mixed> */
    public function filterOptions(): array
    {
        return [
            'provinces' => Province::query()->orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
            'cities' => Outlet::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city')->all(),
            'categories' => Category::query()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'key', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'key' => $c->key, 'name' => $c->name])->all(),
            'pipeline_statuses' => ['new', 'contacted', 'demo', 'won', 'lost'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function rejectionReasons(): array
    {
        return RejectionReason::query()->orderBy('sort_order')->get(['id', 'key', 'label'])
            ->map(fn ($r) => ['id' => $r->id, 'key' => $r->key, 'label' => $r->label])->all();
    }

    /** @return array<string, mixed> */
    public function map(): array
    {
        return [
            'filter_options' => $this->filterOptions(),
            'hud' => $this->dashboard->hud(),
            'provinces_index' => Province::query()->orderBy('name')->get(['id', 'name', 'lat', 'lng'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'lat' => $p->lat, 'lng' => $p->lng])->all(),
            'rejection_reasons' => $this->rejectionReasons(),
            'contact_rules' => $this->settings->contactRules(),
        ];
    }

    /** @return array<string, mixed> */
    public function impact(): array
    {
        $assumptions = $this->settings->impactAssumptions();

        return [
            'assumptions' => $assumptions,
            'defaults' => ImpactCalculator::defaults(),
            'results' => (new ImpactCalculator)->calculate($assumptions),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function dataSources(): array
    {
        return DataSourceResource::collection(DataSource::query()->orderBy('priority')->get())->resolve();
    }

    /** @return list<array<string, mixed>> */
    public function categories(): array
    {
        return CategoryResource::collection(Category::query()->orderBy('sort_order')->orderBy('name')->get())->resolve();
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function schedulerRuns(): array
    {
        $page = SchedulerRun::query()->orderByDesc('started_at')->orderByDesc('id')->paginate(20);

        return [
            'data' => SchedulerRunResource::collection($page->items())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'per_page' => $page->perPage(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function masterOutlets(array $filters): array
    {
        return [
            'outlets' => $this->outlets->paginated($filters),
            'filters' => [
                'search' => $filters['search'] ?? null,
                'province_id' => isset($filters['province_id']) ? (int) $filters['province_id'] : null,
                'geocode_status' => $filters['geocode_status'] ?? null,
            ],
            'review_queue_count' => $this->outlets->reviewCount(),
            'provinces' => Province::query()->orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
        ];
    }
}
