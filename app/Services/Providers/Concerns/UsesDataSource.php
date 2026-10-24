<?php

declare(strict_types=1);

namespace App\Services\Providers\Concerns;

use App\DTO\UsageInfo;
use App\Models\DataSource;

/** Lazy access to the provider's own data_sources row. */
trait UsesDataSource
{
    private ?DataSource $sourceRow = null;

    private bool $sourceLoaded = false;

    abstract public function key(): string;

    public function source(): ?DataSource
    {
        if (! $this->sourceLoaded) {
            $this->sourceRow = DataSource::query()->where('key', $this->key())->first();
            $this->sourceLoaded = true;
        }

        return $this->sourceRow;
    }

    public function refreshSource(): void
    {
        $this->sourceLoaded = false;
        $this->sourceRow = null;
    }

    public function usage(): UsageInfo
    {
        $this->refreshSource();
        $s = $this->source();
        if ($s === null) {
            return new UsageInfo(0, null, null);
        }
        $cost = $s->cost_search_per_1000 !== null
            ? round($s->calls_this_month * $s->cost_search_per_1000 / 1000, 4)
            : null;

        return new UsageInfo((int) $s->calls_this_month, $s->monthly_quota, $cost);
    }
}
