<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use App\Models\GeocodeQueueItem;
use App\Models\SchedulerRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Works through geocode_queue (priority desc), filling prospects.address from reverse geocoding (D-41, D-42). */
class ReverseGeocodeService
{
    public const MAX_ATTEMPTS = 3;

    /** Stop a run after this many transient errors in a row (network down). */
    public const MAX_CONSECUTIVE_ERRORS = 3;

    public function __construct(private readonly GeocoderInterface $geocoder) {}

    /** True when a real geocoder is bound and the feature flag is on. */
    public function available(): bool
    {
        if ($this->geocoder instanceof NullGeocoder) {
            return false;
        }

        return (bool) config('hunter.geocoding.enabled', true);
    }

    public function pendingCount(): int
    {
        return $this->pendingQuery()->count();
    }

    /**
     * Process up to $max items.
     *
     * @return array{processed: int, done: int, failed: int, retry: int, stopped: ?string}
     */
    public function run(int $max): array
    {
        $stats = ['processed' => 0, 'done' => 0, 'failed' => 0, 'retry' => 0, 'stopped' => null];
        $errors = 0;
        foreach ($this->pendingQuery()->limit($max)->get() as $item) {
            $outcome = $this->processItem($item);
            if ($outcome === 'blocked') {
                $stats['stopped'] = 'blocked';
                break;
            }
            $stats['processed']++;
            $stats[match ($outcome) {
                'skipped' => 'done', 'error' => 'retry', default => $outcome
            }]++;
            $errors = $outcome === 'error' ? $errors + 1 : 0;
            if ($errors >= self::MAX_CONSECUTIVE_ERRORS) {
                $stats['stopped'] = 'errors';
                break;
            }
        }

        return $stats;
    }

    /**
     * @return 'done'|'failed'|'retry'|'blocked'|'skipped'|'error'
     */
    public function processItem(GeocodeQueueItem $item): string
    {
        $prospect = $item->prospect;
        if ($prospect === null || filled($prospect->address)) {
            $this->finish($item, 'done');

            return 'skipped'; // nothing left to do
        }
        if ($prospect->lat === null || $prospect->lng === null) {
            return $this->fail($item, 'Prospect has no coordinates');
        }

        try {
            $found = $this->geocoder instanceof DetailedReverseGeocoder
                ? $this->geocoder->reverseDetailed((float) $prospect->lat, (float) $prospect->lng)
                : $this->plain((float) $prospect->lat, (float) $prospect->lng);
        } catch (GeocodingBlockedException) {
            return 'blocked';
        } catch (GeocodingException $e) {
            $this->recordAttempt($item, $e->getMessage(), Carbon::now()->addHour());

            return $item->status === 'failed' ? 'failed' : 'error';
        }

        if ($found === null) {
            $this->recordAttempt($item, 'No address found', Carbon::now()->addDay());

            return $item->status === 'failed' ? 'failed' : 'retry';
        }

        $prospect->forceFill([
            'address' => mb_substr($found['address'], 0, 300),
            'address_source' => 'reverse_geocode',
            'city' => $found['city'] !== null ? mb_substr($found['city'], 0, 80) : $prospect->city,
        ])->save();
        $item->attempts++;
        $this->finish($item, 'done');

        return 'done';
    }

    /** Record a manual/queued run in scheduler_runs. */
    public function recordRun(array $stats, int $durationMs): SchedulerRun
    {
        $status = $stats['stopped'] !== null ? ($stats['done'] > 0 ? 'partial' : 'failed') : 'success';

        return SchedulerRun::query()->create([
            'command' => 'hunter:geocode',
            'status' => $status,
            'started_at' => Carbon::now()->subMilliseconds($durationMs),
            'finished_at' => Carbon::now(),
            'duration_ms' => $durationMs,
            'outlets_total' => 0, 'outlets_ok' => 0, 'outlets_failed' => 0,
            'prospects_new' => 0, 'prospects_updated' => $stats['done'], 'prospects_closed' => 0,
            'api_calls' => $stats['done'] + $stats['failed'] + $stats['retry'],
            'error' => $stats['stopped'] !== null ? 'Stopped: '.$stats['stopped'] : null,
            'details' => $stats,
        ]);
    }

    /** @return array{address: string, city: ?string}|null */
    private function plain(float $lat, float $lng): ?array
    {
        $address = $this->geocoder->reverse($lat, $lng);

        return $address === null ? null : ['address' => $address, 'city' => null];
    }

    private function fail(GeocodeQueueItem $item, string $why): string
    {
        $item->forceFill(['status' => 'failed', 'last_error' => $why, 'attempts' => $item->attempts + 1])->save();

        return 'failed';
    }

    private function recordAttempt(GeocodeQueueItem $item, string $error, Carbon $retryAt): void
    {
        $item->attempts++;
        $item->last_error = mb_substr($error, 0, 500);
        if ($item->attempts >= self::MAX_ATTEMPTS) {
            $item->status = 'failed';
        } else {
            $item->available_at = $retryAt;
        }
        $item->save();
    }

    private function finish(GeocodeQueueItem $item, string $status): void
    {
        $item->forceFill(['status' => $status, 'done_at' => Carbon::now(), 'last_error' => null])->save();
    }

    private function pendingQuery(): Builder
    {
        return GeocodeQueueItem::query()
            ->with('prospect')
            ->where('status', 'pending')
            ->where('available_at', '<=', Carbon::now())
            ->orderByDesc('priority')
            ->orderBy('id');
    }
}
