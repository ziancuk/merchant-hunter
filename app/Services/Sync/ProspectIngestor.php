<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\DTO\BusinessDTO;
use App\Enums\LifecycleStatus;
use App\Models\Category;
use App\Models\GeocodeQueueItem;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Services\Geo\BoundingBox;
use App\Services\Scoring\ProspectScorer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns a provider answer for one outlet into prospect rows: dedupe vs outlets, idempotent upsert by
 * (provider, place_id), lifecycle, distance/neighbour/density metrics, scoring and geocode queueing.
 * Bulk inserts and in-memory spatial grids keep a 14k-row seed fast (no per-row metric queries).
 */
class ProspectIngestor
{
    private const INSERT_CHUNK = 100;

    public function __construct(
        private readonly ProspectScorer $scorer,
        private readonly LifecycleDetector $lifecycle,
    ) {}

    /**
     * @param  list<BusinessDTO>  $businesses
     * @param  bool  $fresh  the provider answered live (not stale cache): only then misses are counted
     * @param  bool  $detectMissing  false for partial answers (e.g. simulated "second run" extras)
     */
    public function ingest(
        Outlet $outlet,
        string $provider,
        array $businesses,
        SyncContext $ctx,
        bool $isBaseline,
        bool $fresh = true,
        bool $detectMissing = true,
        ?CarbonInterface $runStartedAt = null,
        ?int $runId = null,
    ): OutletSyncResult {
        $now = Carbon::now();
        $runStartedAt ??= $now;
        $result = new OutletSyncResult(provider: $provider, fetched: count($businesses));

        $seen = [];
        foreach ($businesses as $b) {
            $seen[$b->placeId] = true;
        }
        $split = $ctx->deduplicator->filter($businesses);
        $result->excluded = count($split['excluded']);
        $kept = array_values(array_filter(
            $split['kept'],
            fn (BusinessDTO $b) => $ctx->categoriesByKey->has($b->categoryKey) && is_finite($b->lat) && is_finite($b->lng),
        ));

        DB::transaction(function () use ($outlet, $provider, $kept, $seen, $ctx, $isBaseline, $fresh, $detectMissing, $now, $runStartedAt, $runId, $result) {
            $this->upsert($outlet, $provider, $kept, $ctx, $isBaseline, $now, $result);
            if ($detectMissing && $fresh) {
                $result->closed += $this->lifecycle->processMissing($outlet, $provider, $seen, $now, $runStartedAt, $runId);
            }
        });

        return $result;
    }

    /** @param list<BusinessDTO> $kept */
    private function upsert(Outlet $outlet, string $provider, array $kept, SyncContext $ctx, bool $isBaseline, CarbonInterface $now, OutletSyncResult $result): void
    {
        if ($kept === []) {
            return;
        }
        $existing = $this->loadExisting($provider, array_map(fn (BusinessDTO $b) => $b->placeId, $kept));
        $grid = $this->densityGrid($outlet);

        $newRows = [];
        $needsAddress = [];
        $items = [];

        foreach ($kept as $b) {
            /** @var Category $category */
            $category = $ctx->categoriesByKey[$b->categoryKey];
            /** @var Prospect|null $ex */
            $ex = $existing[$b->placeId] ?? null;
            if ($ex === null && $b->isClosedPermanently) {
                continue; // never create a prospect that is already permanently closed
            }

            $attrs = $this->attributes($b, $category, $provider, $now);
            $selfKey = $ex !== null ? 'id:'.$ex->id : 'new:'.$b->placeId;

            if ($ex === null) {
                $attrs += [
                    'address' => $b->address,
                    'address_source' => filled($b->address) ? 'source' : 'none',
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'missed_runs' => 0,
                    'lifecycle_status' => LifecycleStatus::Open->value,
                    'is_baseline' => $isBaseline,
                    'is_new_open' => ! $isBaseline,
                ];
                $grid->add($selfKey, $b->lat, $b->lng);
            } else {
                if (filled($b->address)) {
                    $attrs['address'] = $b->address;
                    $attrs['address_source'] = 'source';
                }
                $attrs['last_seen_at'] = $now;
                $attrs['missed_runs'] = 0;
                $attrs['is_new_open'] = $ex->is_new_open && $this->lifecycle->isNewOpenStillValid($ex->first_seen_at, $now);
                if ($b->isClosedPermanently) {
                    if ($ex->lifecycle_status !== LifecycleStatus::Closed) {
                        $result->closed++;
                    }
                    $attrs['lifecycle_status'] = LifecycleStatus::Closed->value;
                    $attrs['closed_at'] = $ex->closed_at ?? $now;
                    $attrs['closed_reason'] = 'permanent';
                    $grid->remove($selfKey);
                } else {
                    if ($ex->lifecycle_status === LifecycleStatus::Closed) {
                        $attrs['lifecycle_status'] = LifecycleStatus::Open->value;
                        $attrs['closed_at'] = null;
                        $attrs['closed_reason'] = null;
                    }
                    $grid->add($selfKey, $b->lat, $b->lng);
                }
            }

            $items[] = [$b, $category, $ex, $attrs, $selfKey];
        }

        // Second pass: the grid now holds the whole batch, so densities do not depend on row order.
        foreach ($items as [$b, $category, $ex, $attrs, $selfKey]) {
            $metrics = $ctx->metrics->outletMetrics($b->lat, $b->lng);
            $city = $metrics['city'];
            if ($ex !== null && $ex->address_source === 'reverse_geocode' && filled($ex->city)) {
                $city = $ex->city; // reverse geocoding overrides the outlet-derived city (D-70)
            }
            $attrs += [
                'nearest_outlet_id' => $metrics['nearest_outlet_id'],
                'nearest_outlet_distance_m' => $metrics['nearest_outlet_distance_m'],
                'neighbor_count_500m' => $metrics['neighbor_count_500m'],
                'area_density' => $ctx->metrics->density($grid, $b->lat, $b->lng, $selfKey),
                'province_id' => $metrics['province_id'],
                'city' => $city !== null ? mb_substr($city, 0, 80) : null,
            ];

            $merged = $ex !== null ? array_merge($ex->getAttributes(), $attrs) : $attrs;
            if (($merged['lifecycle_status'] ?? 'open') === LifecycleStatus::Closed->value) {
                // Closed prospects score 0 (TRD §7), whatever the scorer implementation does.
                $attrs['score'] = 0;
                $attrs['score_breakdown'] = null;
            } else {
                $score = $this->scorer->score(ScoreInputFactory::fromAttributes($merged, (int) $category->weight, $now));
                $attrs['score'] = $score->score;
                $attrs['score_breakdown'] = $score->breakdown === [] ? null : $score->breakdown;
            }

            if ($ex === null) {
                $newRows[$b->placeId] = $attrs;
            } else {
                $ex->fill($attrs);
                $dirty = array_diff(array_keys($ex->getDirty()), ['last_seen_at', 'fetched_at', 'missed_runs', 'updated_at']);
                if ($dirty !== []) {
                    $result->updated++;
                }
                $ex->save();
                if (blank($merged['address'] ?? null)) {
                    $needsAddress[$ex->id] = (int) $attrs['score'];
                }
            }
        }

        $result->new += count($newRows);
        $ids = $this->insertNew($provider, $newRows, $now);
        foreach ($ids as $placeId => $id) {
            if (blank($newRows[$placeId]['address'] ?? null)) {
                $needsAddress[$id] = (int) $newRows[$placeId]['score'];
            }
        }
        $this->enqueueGeocoding($needsAddress, $now);
    }

    /**
     * @param  list<string>  $placeIds
     * @return array<string, Prospect>
     */
    private function loadExisting(string $provider, array $placeIds): array
    {
        $out = [];
        foreach (array_chunk($placeIds, 400) as $chunk) {
            foreach (Prospect::query()->where('provider', $provider)->whereIn('place_id', $chunk)->get() as $p) {
                $out[$p->place_id] = $p;
            }
        }

        return $out;
    }

    /** Open prospects around the outlet (DB state before this batch), keyed so the batch can replace them. */
    private function densityGrid(Outlet $outlet): PointGrid
    {
        $radius = (float) config('hunter.radius_m', 2000) + ProspectMetrics::NEIGHBOR_RADIUS_M + 100;
        $box = BoundingBox::around((float) $outlet->lat, (float) $outlet->lng, $radius);
        $grid = new PointGrid;
        $rows = DB::table('prospects')
            ->where('lifecycle_status', LifecycleStatus::Open->value)
            ->whereBetween('lat', [$box->south, $box->north])
            ->whereBetween('lng', [$box->west, $box->east])
            ->get(['id', 'lat', 'lng']);
        foreach ($rows as $r) {
            $grid->add('id:'.$r->id, (float) $r->lat, (float) $r->lng);
        }

        return $grid;
    }

    /** @return array<string, mixed> */
    private function attributes(BusinessDTO $b, Category $category, string $provider, CarbonInterface $now): array
    {
        $fetchedAt = $b->fetchedAt !== null ? Carbon::instance($b->fetchedAt) : Carbon::instance($now);

        return [
            'provider' => $provider,
            'place_id' => $b->placeId,
            'name' => mb_substr($b->name, 0, 200),
            'category_id' => $category->id,
            'lat' => round($b->lat, 6),
            'lng' => round($b->lng, 6),
            'phone' => $b->phone !== null ? mb_substr($b->phone, 0, 40) : null,
            'phone_simulated' => $b->phone !== null && $b->isSimulated('phone'),
            'website' => $b->website !== null ? mb_substr($b->website, 0, 255) : null,
            'rating' => $b->rating,
            'rating_simulated' => $b->rating !== null && $b->isSimulated('rating'),
            'review_count' => $b->reviewCount,
            'reviews_simulated' => $b->reviewCount !== null && $b->isSimulated('review_count'),
            'opening_hours' => $b->openingHours !== null ? mb_substr($b->openingHours, 0, 255) : null,
            'is_simulated' => $provider === 'mock',
            'fetched_at' => $fetchedAt,
            // Google content is a cache: everything but place_id expires 30 days after fetch (D-47).
            'expires_at' => $provider === 'google' ? $fetchedAt->copy()->addDays(30) : null,
            'needs_refresh' => false,
            'raw_tags' => $b->rawTags === [] ? null : $b->rawTags,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows  keyed by place_id
     * @return array<string, int> place_id => id
     */
    private function insertNew(string $provider, array $rows, CarbonInterface $now): array
    {
        if ($rows === []) {
            return [];
        }
        $stamp = $now->format('Y-m-d H:i:s');
        foreach (array_chunk($rows, self::INSERT_CHUNK, true) as $chunk) {
            $payload = [];
            foreach ($chunk as $attrs) {
                $payload[] = $this->toInsertRow($attrs, $stamp);
            }
            Prospect::query()->insertOrIgnore($payload); // a concurrent run may have inserted the same (provider, place_id)
        }

        $ids = [];
        foreach (array_chunk(array_keys($rows), 400) as $placeIds) {
            foreach (Prospect::query()->where('provider', $provider)->whereIn('place_id', $placeIds)->get(['id', 'place_id']) as $p) {
                $ids[$p->place_id] = $p->id;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function toInsertRow(array $attrs, string $stamp): array
    {
        foreach ($attrs as $k => $v) {
            if ($v instanceof CarbonInterface) {
                $attrs[$k] = $v->format('Y-m-d H:i:s');
            } elseif (is_array($v)) {
                $attrs[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($v)) {
                $attrs[$k] = (int) $v;
            }
        }
        // Keep one fixed column set so every row of a bulk insert has identical keys.
        $attrs += ['raw_tags' => null, 'score_breakdown' => null, 'expires_at' => null];
        ksort($attrs);

        return $attrs + ['created_at' => $stamp, 'updated_at' => $stamp];
    }

    /** @param array<int, int> $scoreById prospect id => score (used as queue priority, D-42) */
    private function enqueueGeocoding(array $scoreById, CarbonInterface $now): void
    {
        if ($scoreById === []) {
            return;
        }
        $stamp = $now->format('Y-m-d H:i:s');
        $rows = [];
        foreach ($scoreById as $id => $score) {
            $rows[] = [
                'prospect_id' => $id, 'priority' => max(0, min(100, $score)), 'status' => 'pending', 'attempts' => 0,
                'available_at' => $stamp, 'created_at' => $stamp, 'updated_at' => $stamp,
            ];
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            GeocodeQueueItem::query()->insertOrIgnore($chunk);
        }
    }
}
