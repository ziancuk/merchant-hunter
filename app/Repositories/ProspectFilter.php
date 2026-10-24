<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\LifecycleStatus;
use App\Models\Prospect;
use Illuminate\Database\Eloquent\Builder;

/** Shared prospect filter (map, pipeline, export). Built from validated request input. */
final class ProspectFilter
{
    public const KEYS = [
        'province_id', 'city', 'category_id', 'score_min', 'score_max', 'rating_min',
        'has_phone', 'pipeline_status', 'max_distance_m', 'new_open_only', 'include_closed',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(private readonly array $values = []) {}

    /** @param array<string, mixed> $validated */
    public static function from(array $validated): self
    {
        return new self(array_intersect_key($validated, array_flip(self::KEYS)));
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    /** @return list<int> */
    public function categoryIds(): array
    {
        return array_values(array_map('intval', (array) ($this->values['category_id'] ?? [])));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values;
    }

    /** @param Builder<Prospect> $q */
    public function apply(Builder $q): Builder
    {
        $v = $this->values;

        if (! filter_var($v['include_closed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $q->where('prospects.lifecycle_status', LifecycleStatus::Open->value);
        }
        if (isset($v['province_id'])) {
            $q->where('prospects.province_id', (int) $v['province_id']);
        }
        if (filled($v['city'] ?? null)) {
            $q->where('prospects.city', (string) $v['city']);
        }
        if (! empty($v['category_id'])) {
            $q->whereIn('prospects.category_id', $this->categoryIds());
        }
        if (isset($v['score_min'])) {
            $q->where('prospects.score', '>=', (int) $v['score_min']);
        }
        if (isset($v['score_max'])) {
            $q->where('prospects.score', '<=', (int) $v['score_max']);
        }
        if (isset($v['rating_min'])) {
            $q->where('prospects.rating', '>=', (float) $v['rating_min']);
        }
        if (isset($v['has_phone'])) {
            filter_var($v['has_phone'], FILTER_VALIDATE_BOOLEAN)
                ? $q->whereNotNull('prospects.phone')->where('prospects.phone', '!=', '')
                : $q->where(fn ($w) => $w->whereNull('prospects.phone')->orWhere('prospects.phone', ''));
        }
        if (! empty($v['pipeline_status'])) {
            $q->whereIn('prospects.pipeline_status', (array) $v['pipeline_status']);
        }
        if (isset($v['max_distance_m'])) {
            $q->where('prospects.nearest_outlet_distance_m', '<=', (int) $v['max_distance_m']);
        }
        if (filter_var($v['new_open_only'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $q->where('prospects.is_new_open', true);
        }

        return $q;
    }
}
