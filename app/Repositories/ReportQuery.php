<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ScoreBand;
use App\Services\Export\ProspectCsvExport;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/** Read-only, wide prospect table for the Laporan page (same filter and joins as the exports). */
final class ReportQuery
{
    /** Sortable columns: request key => SQL expression. */
    public const SORTS = [
        'score' => 'prospects.score',
        'name' => 'prospects.name',
        'category' => 'categories.name',
        'city' => 'prospects.city',
        'province' => 'provinces.name',
        'rating' => 'prospects.rating',
        'review_count' => 'prospects.review_count',
        'status' => 'prospects.pipeline_status',
        'last_contacted_at' => 'prospects.last_contacted_at',
        'distance' => 'prospects.nearest_outlet_distance_m',
    ];

    /** Columns where "no value" must stay last in either direction. */
    private const NULLS_LAST = ['rating', 'review_count', 'last_contacted_at', 'distance', 'city', 'province'];

    public function __construct(private readonly ProspectCsvExport $base) {}

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function page(ProspectFilter $filter, string $sort, string $dir, int $perPage): LengthAwarePaginator
    {
        $key = array_key_exists($sort, self::SORTS) ? $sort : 'score';
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        $q = $this->base->query($filter);
        if (in_array($key, self::NULLS_LAST, true)) {
            $q->orderByRaw(self::SORTS[$key].' is null');
        }
        $q->orderBy(self::SORTS[$key], $dir)->orderBy('prospects.id');

        return $q->paginate($perPage)->through(fn (object $r): array => self::present($r));
    }

    /** @return array<string, mixed> */
    public static function present(object $r): array
    {
        return [
            'id' => (int) $r->id,
            'name' => $r->name,
            'category' => $r->category_name,
            'city' => $r->city,
            'province' => $r->province_name,
            'address' => $r->address,
            'phone' => $r->phone,
            'phone_simulated' => (bool) $r->phone_simulated,
            'rating' => $r->rating === null ? null : (float) $r->rating,
            'review_count' => $r->review_count === null ? null : (int) $r->review_count,
            'score' => (int) $r->score,
            'score_band' => ScoreBand::fromScore((int) $r->score)->value,
            'pipeline_status' => $r->pipeline_status,
            'handled_by' => $r->handler_name,
            'last_contacted_at' => $r->last_contacted_at === null ? null : Carbon::parse($r->last_contacted_at, 'UTC')->toIso8601ZuluString(),
            'distance_m' => $r->nearest_outlet_distance_m === null ? null : (int) $r->nearest_outlet_distance_m,
        ];
    }
}
