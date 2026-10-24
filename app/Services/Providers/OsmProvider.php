<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\DTO\ConnectionStatus;
use App\DTO\OutletPoint;
use App\DTO\ProviderResult;
use App\Models\ApiCache;
use App\Models\Category;
use App\Services\Providers\Concerns\UsesDataSource;
use App\Services\Providers\Osm\OverpassClient;
use App\Services\Providers\Osm\OverpassMapper;
use App\Services\Providers\Osm\OverpassQuery;
use Carbon\CarbonImmutable;

/** OpenStreetMap via Overpass (D-30..D-35): one query per outlet, cached, tolerant to 429/504. */
class OsmProvider implements DataProvider, SupportsCacheBypass
{
    use UsesDataSource;

    /** A cache entry younger than this answers without a request (re-run protection); it is not "fresh". */
    public const RECENT_CACHE_MINUTES = 360;

    private bool $ignoreCache = false;

    public function __construct(private readonly OverpassClient $client) {}

    public function key(): string
    {
        return 'osm';
    }

    public function isAvailable(): bool
    {
        return (bool) $this->source()?->is_active;
    }

    public function ignoreCache(bool $ignore = true): static
    {
        $this->ignoreCache = $ignore;

        return $this;
    }

    public function searchNearby(OutletPoint $center, int $radiusMeters, array $categories): ProviderResult
    {
        $query = OverpassQuery::build($center->lat, $center->lng, $radiusMeters, $categories);
        $cacheKey = hash('sha256', $query.sprintf('|%.5f,%.5f', $center->lat, $center->lng));
        $entry = ApiCache::query()->where('cache_key', $cacheKey)->first();
        $usable = $entry !== null && $entry->expires_at->isFuture();

        if ($usable && ! $this->ignoreCache && $entry->fetched_at->gt(now()->subMinutes(self::RECENT_CACHE_MINUTES))) {
            return $this->fromCache($entry, $categories);
        }

        try {
            $json = $this->client->execute($query);
        } catch (ProviderException $e) {
            if ($usable) {
                // 429/504 and friends: serve the stale answer, flagged so lifecycle logic ignores it.
                return $this->fromCache($entry, $categories, $this->client->lastRequestCount);
            }
            throw $e;
        }

        $this->store($cacheKey, $json);
        $this->bumpCalls($this->client->lastRequestCount);
        $now = CarbonImmutable::now();

        return new ProviderResult(OverpassMapper::map($json, $categories, $now), false, true, $this->client->lastRequestCount);
    }

    public function testConnection(): ConnectionStatus
    {
        $start = microtime(true);
        $status = $this->client->status();
        $ms = (int) round((microtime(true) - $start) * 1000);

        return $status !== null
            ? new ConnectionStatus(true, 'Overpass reachable: '.mb_substr(preg_replace('/\s+/', ' ', $status) ?? $status, 0, 160), $ms)
            : new ConnectionStatus(false, 'Overpass status endpoint is unreachable', $ms);
    }

    /** @param list<Category> $categories */
    private function fromCache(ApiCache $entry, array $categories, int $apiCalls = 0): ProviderResult
    {
        $json = $this->decodePayload($entry->payload);
        $fetchedAt = CarbonImmutable::instance($entry->fetched_at);

        return new ProviderResult(OverpassMapper::map($json, $categories, $fetchedAt), true, false, $apiCalls);
    }

    /** @param array<string, mixed> $json */
    private function store(string $cacheKey, array $json): void
    {
        $payload = base64_encode((string) gzencode((string) json_encode($json), 6));
        ApiCache::query()->updateOrCreate(['cache_key' => $cacheKey], [
            'provider' => 'osm',
            'payload' => $payload,
            'fetched_at' => now(),
            'expires_at' => now()->addDays((int) config('hunter.overpass.cache_ttl_days', 7)),
        ]);
    }

    /** @return array<string, mixed> */
    private function decodePayload(string $payload): array
    {
        $raw = base64_decode($payload, true);
        $json = $raw === false ? false : @gzdecode($raw);
        $data = $json === false ? null : json_decode($json, true);

        return is_array($data) ? $data : ['elements' => []];
    }

    private function bumpCalls(int $calls): void
    {
        if ($calls > 0 && $this->source() !== null) {
            $this->source()->increment('calls_this_month', $calls);
        }
    }
}
