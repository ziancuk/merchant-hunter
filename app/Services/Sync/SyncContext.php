<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Category;
use App\Models\Outlet;
use App\Services\Dedupe\Deduplicator;
use App\Services\Dedupe\OutletIndex;
use Illuminate\Support\Collection;

/** Read-only lookups shared by every outlet of a run (outlet index, dedupe, categories). */
final class SyncContext
{
    /**
     * @param  Collection<string, Category>  $categoriesByKey
     */
    public function __construct(
        public readonly OutletIndex $outlets,
        public readonly Deduplicator $deduplicator,
        public readonly ProspectMetrics $metrics,
        public readonly Collection $categoriesByKey,
    ) {}

    public static function load(): self
    {
        $index = new OutletIndex(Outlet::query()->located()->get());

        return new self($index, new Deduplicator($index), new ProspectMetrics($index), Category::query()->get()->keyBy('key'));
    }

    /** @return list<Category> active categories, in sort order */
    public function activeCategories(): array
    {
        return $this->categoriesByKey->filter(fn (Category $c) => $c->is_active)->sortBy('sort_order')->values()->all();
    }
}
