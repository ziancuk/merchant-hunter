<?php

declare(strict_types=1);

namespace App\Services\Providers\Osm;

use App\Services\Providers\ProviderException;
use App\Services\Providers\Sleeper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Transport for Overpass (D-31): fixed User-Agent, spacing between requests, retry with backoff + jitter
 * (primary and mirror alternate), circuit breaker after consecutive failed searches.
 */
class OverpassClient
{
    public const KEY_LAST_REQUEST = 'hunter.overpass.last_request_at';

    public const KEY_FAILURES = 'hunter.overpass.consecutive_failures';

    public const KEY_CIRCUIT_UNTIL = 'hunter.overpass.circuit_open_until';

    /** How long the breaker stays open once tripped. */
    public const CIRCUIT_OPEN_MINUTES = 15;

    /** HTTP calls made by the latest execute(), for api_calls accounting. */
    public int $lastRequestCount = 0;

    public function __construct(private readonly Sleeper $sleeper) {}

    public function userAgent(): string
    {
        return 'pos-merchant-hunter/1.0 ('.config('hunter.overpass.contact_email').')';
    }

    public function circuitOpen(): bool
    {
        $until = Cache::get(self::KEY_CIRCUIT_UNTIL);

        return $until !== null && Carbon::now()->timestamp < (int) $until;
    }

    /**
     * Run a query and return the decoded JSON body.
     *
     * @return array<string, mixed>
     *
     * @throws ProviderException
     */
    public function execute(string $query): array
    {
        $this->lastRequestCount = 0;
        if ($this->circuitOpen()) {
            throw new ProviderException('Overpass circuit breaker is open after repeated failures');
        }

        $cfg = config('hunter.overpass');
        $endpoints = array_values(array_filter([$cfg['endpoint'] ?? null, $cfg['mirror'] ?? null]));
        $backoff = array_values($cfg['backoff_seconds'] ?? [30, 60, 120]);
        $last = null;

        for ($attempt = 0; $attempt <= count($backoff); $attempt++) {
            if ($attempt > 0) {
                $base = (float) $backoff[$attempt - 1];
                $this->sleeper->sleep($base + $base * 0.1 * (random_int(0, 1000) / 1000));
            }
            $this->throttle((float) ($cfg['delay_seconds'] ?? 5));
            $endpoint = $endpoints[$attempt % count($endpoints)];
            $this->lastRequestCount++;
            try {
                $body = $this->request($endpoint, $query, (int) ($cfg['timeout_seconds'] ?? 90));
                Cache::forget(self::KEY_FAILURES);

                return $body;
            } catch (ProviderException $e) {
                $last = $e;
                if (! $e->retryable) {
                    break;
                }
            }
        }

        $this->recordFailure();

        throw $last ?? new ProviderException('Overpass request failed');
    }

    /** Best-effort slot check on /api/status (optional step of D-31). */
    public function status(): ?string
    {
        $endpoint = (string) config('hunter.overpass.endpoint');
        $url = preg_replace('#/interpreter/?$#', '/status', $endpoint);
        try {
            $r = Http::withUserAgent($this->userAgent())->timeout(10)->get($url);
        } catch (ConnectionException) {
            return null;
        }

        return $r->successful() ? trim($r->body()) : null;
    }

    /** @return array<string, mixed> */
    private function request(string $endpoint, string $query, int $timeout): array
    {
        try {
            $response = Http::withUserAgent($this->userAgent())
                ->timeout($timeout)
                ->asForm()
                ->post($endpoint, ['data' => $query]);
        } catch (ConnectionException $e) {
            throw new ProviderException('Overpass connection failed: '.$e->getMessage(), true);
        }

        return $this->decode($response);
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        $status = $response->status();
        if (in_array($status, [406, 429, 502, 503, 504], true) || $status >= 500) {
            throw new ProviderException("Overpass answered HTTP {$status}", true, $status);
        }
        if (! $response->successful()) {
            throw new ProviderException("Overpass rejected the request (HTTP {$status})", false, $status);
        }
        $json = json_decode($response->body(), true);
        if (! is_array($json) || ! isset($json['elements']) || ! is_array($json['elements'])) {
            throw new ProviderException('Overpass returned an invalid JSON body', true, $status);
        }
        $remark = (string) ($json['remark'] ?? '');
        if ($json['elements'] === [] && $remark !== '' && preg_match('/runtime error|timed out|out of memory/i', $remark)) {
            throw new ProviderException('Overpass reported: '.$remark, true, $status);
        }

        return $json;
    }

    /** Keep at least delay_seconds between two requests, across jobs (shared via the cache). */
    private function throttle(float $delay): void
    {
        $now = Carbon::now()->getPreciseTimestamp(3) / 1000;
        $last = Cache::get(self::KEY_LAST_REQUEST);
        $wait = $last !== null ? $delay - ($now - (float) $last) : 0.0;
        if ($wait > 0) {
            $this->sleeper->sleep($wait);
            $now += $wait;
        }
        Cache::forever(self::KEY_LAST_REQUEST, $now);
    }

    private function recordFailure(): void
    {
        Cache::add(self::KEY_FAILURES, 0);
        $failures = (int) Cache::increment(self::KEY_FAILURES);
        if ($failures >= (int) config('hunter.overpass.circuit_breaker_failures', 5)) {
            Cache::put(self::KEY_CIRCUIT_UNTIL, Carbon::now()->addMinutes(self::CIRCUIT_OPEN_MINUTES)->timestamp, now()->addMinutes(self::CIRCUIT_OPEN_MINUTES + 1));
            Cache::forget(self::KEY_FAILURES);
        }
    }
}
