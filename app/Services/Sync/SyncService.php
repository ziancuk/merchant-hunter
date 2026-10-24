<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\DTO\OutletPoint;
use App\DTO\ProviderResult;
use App\Models\DataSource;
use App\Models\Outlet;
use App\Models\SchedulerRun;
use App\Models\SchedulerRunOutlet;
use App\Models\Setting;
use App\Services\Providers\DataProvider;
use App\Services\Providers\ProviderRegistry;
use App\Services\Providers\SupportsCacheBypass;
use Illuminate\Support\Carbon;
use Throwable;

/** Orchestrates one outlet: providers (first success wins) → dedupe → upsert → lifecycle → metrics → score → queue. */
class SyncService
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ProspectIngestor $ingestor,
        private readonly LifecycleDetector $lifecycle,
    ) {}

    public function multiProvider(): bool
    {
        return (bool) Setting::read('sync.multi_provider', config('hunter.settings_defaults.sync.multi_provider', false));
    }

    public function syncOutlet(Outlet $outlet, ?SyncContext $ctx = null, ?string $onlyProvider = null, bool $fresh = false, ?SchedulerRun $run = null): OutletSyncResult
    {
        $started = microtime(true);
        $ctx ??= SyncContext::load();
        $point = OutletPoint::fromModel($outlet);
        $categories = $ctx->activeCategories();

        /** @var list<array{provider: string, result: ProviderResult}> $answers */
        $answers = [];
        $errors = [];
        $providers = $this->registry->resolveFor($outlet, $onlyProvider);
        if ($providers === []) {
            return new OutletSyncResult(status: 'failed', provider: $onlyProvider ?? '', error: 'No available data provider', durationMs: $this->ms($started));
        }

        $multi = $this->multiProvider();
        foreach ($providers as $provider) {
            try {
                if ($provider instanceof SupportsCacheBypass) {
                    $provider->ignoreCache($fresh);
                }
                $result = $provider->searchNearby($point, (int) config('hunter.radius_m', 2000), $categories);
                $answers[] = ['provider' => $provider->key(), 'result' => $result];
                $this->markSource($provider, 'ok', null);
            } catch (Throwable $e) {
                $errors[] = $provider->key().': '.$e->getMessage();
                $this->markSource($provider, 'error', $e->getMessage());

                continue;
            }
            if (! $multi) {
                break; // first provider that answers wins (D-63)
            }
        }

        if ($answers === []) {
            return new OutletSyncResult(status: 'failed', provider: implode(',', array_map(fn (DataProvider $p) => $p->key(), $providers)), error: implode(' | ', $errors), durationMs: $this->ms($started));
        }

        $result = $this->ingestAnswers($outlet, $answers, $ctx, $run);
        $result->error = $errors === [] ? null : implode(' | ', $errors);
        $result->durationMs = $this->ms($started);

        return $result;
    }

    /**
     * Ingest answers that are already in hand (also used by the offline seed with MockProvider).
     *
     * @param  list<array{provider: string, result: ProviderResult}>  $answers  ordered by provider priority
     */
    public function ingestAnswers(Outlet $outlet, array $answers, SyncContext $ctx, ?SchedulerRun $run = null, bool $detectMissing = true): OutletSyncResult
    {
        $isBaseline = $this->lifecycle->isBaseline($outlet);
        $runStarted = $run?->started_at ?? Carbon::now();

        // Cross-provider merge (TRD §5 point 5) only when several providers answered.
        $groups = [];
        if (count($answers) > 1) {
            $merged = $ctx->deduplicator->mergeAcrossProviders(array_map(fn (array $a) => $a['result']->businesses, $answers));
            foreach ($merged as $b) {
                $groups[$b->provider][] = $b;
            }
        } else {
            $groups[$answers[0]['provider']] = $answers[0]['result']->businesses;
        }

        $total = new OutletSyncResult(provider: implode(',', array_column($answers, 'provider')));
        $allCached = true;
        foreach ($answers as $answer) {
            $res = $answer['result'];
            $allCached = $allCached && $res->cached;
            $part = $this->ingestor->ingest(
                $outlet, $answer['provider'], $groups[$answer['provider']] ?? [], $ctx,
                $isBaseline, fresh: $res->fresh && ! $res->cached, detectMissing: $detectMissing,
                runStartedAt: $runStarted, runId: $run?->id,
            );
            $part->apiCalls = $res->apiCalls;
            $total->add($part);
        }
        $total->status = $allCached ? 'cached' : 'ok';

        $outlet->forceFill([
            'last_synced_at' => Carbon::now(),
            'baseline_done_at' => $outlet->baseline_done_at ?? Carbon::now(),
        ])->save();

        return $total;
    }

    /** Persist the per-outlet line of a run and add its API calls to the run (atomic increments). */
    public function recordOutlet(SchedulerRun $run, Outlet $outlet, OutletSyncResult $r): SchedulerRunOutlet
    {
        if ($r->apiCalls > 0) {
            SchedulerRun::query()->whereKey($run->id)->increment('api_calls', $r->apiCalls);
        }

        return SchedulerRunOutlet::query()->updateOrCreate(
            ['scheduler_run_id' => $run->id, 'outlet_id' => $outlet->id],
            [
                'provider' => mb_substr($r->provider, 0, 20),
                'status' => $r->status,
                'fetched_count' => $r->fetched,
                'new_count' => $r->new,
                'updated_count' => $r->updated,
                'closed_count' => $r->closed,
                'error' => $r->error !== null ? mb_substr($r->error, 0, 1000) : null,
                'duration_ms' => $r->durationMs,
            ],
        );
    }

    private function markSource(DataProvider $provider, string $status, ?string $error): void
    {
        DataSource::query()->where('key', $provider->key())->update([
            'last_run_at' => Carbon::now(),
            'last_status' => $status,
            'last_error' => $error !== null ? mb_substr($error, 0, 1000) : null,
        ]);
    }

    private function ms(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
