<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\DTO\OutletPoint;
use App\DTO\ProviderResult;
use App\Enums\LifecycleStatus;
use App\Models\Outlet;
use App\Models\Prospect;
use App\Models\SchedulerRun;
use App\Services\Providers\MockProvider;
use App\Services\Providers\SeededRandom;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * `hunter:seed-prospects --offline`: fills the database from MockProvider for every active located outlet
 * (baseline run), then simulates a "second run" with ~3% new prospects (some "new open") and a few closures
 * so the demo has "baru buka" data (TRD §6). Deterministic for a given seed. Calls MockProvider directly
 * (D-63), never through the registry.
 */
class OfflineSeeder
{
    public const SECOND_RUN_NEW_RATIO = 0.03;

    public const CLOSED_RATIO = 0.004;

    public function __construct(
        private readonly SyncService $sync,
        private readonly ProspectRescorer $rescorer,
    ) {}

    /**
     * @param  Closure(string): void|null  $progress
     * @return SchedulerRun|null null when there is no active located outlet
     */
    public function seed(int $seed = 42, ?Closure $progress = null): ?SchedulerRun
    {
        $outlets = Outlet::query()->active()->located()->orderBy('id')->get();
        if ($outlets->isEmpty()) {
            return null;
        }
        $say = $progress ?? fn (string $m) => null;
        $started = microtime(true);

        $run = SchedulerRun::query()->create([
            'command' => SyncRunner::COMMAND,
            'status' => 'running',
            'started_at' => Carbon::now(),
            'outlets_total' => $outlets->count(),
            'outlets_ok' => 0, 'outlets_failed' => 0,
            'prospects_new' => 0, 'prospects_updated' => 0, 'prospects_closed' => 0,
            'api_calls' => 0,
            'details' => ['mode' => 'offline_seed', 'seed' => $seed, 'provider' => 'mock'],
        ]);

        $totals = new OutletSyncResult;
        $secondRun = ['new' => 0, 'closed' => 0];

        DB::transaction(function () use ($outlets, $seed, $run, $say, $totals, &$secondRun) {
            $ctx = SyncContext::load();
            $categories = $ctx->activeCategories();
            $mock = new MockProvider($seed);
            $radius = (int) config('hunter.radius_m', 2000);

            foreach ($outlets as $i => $outlet) {
                $t = microtime(true);
                $answer = $mock->searchNearby(OutletPoint::fromModel($outlet), $radius, $categories);
                $result = $this->sync->ingestAnswers($outlet, [['provider' => 'mock', 'result' => $answer]], $ctx, $run);
                $result->durationMs = (int) round((microtime(true) - $t) * 1000);
                $this->sync->recordOutlet($run, $outlet, $result);
                $totals->add($result);
                if (($i + 1) % 50 === 0) {
                    $say(sprintf('  %d/%d outlets, %d prospects', $i + 1, $outlets->count(), $totals->new));
                }
            }

            $secondRun = $this->simulateSecondRun($outlets->all(), $ctx, $seed);
            $say('  second run simulated: '.$secondRun['new'].' new, '.$secondRun['closed'].' closed');

            // Densities of early outlets ignore later outlets' prospects: rebuild everything once, then score.
            $this->rescorer->run(true);
        });

        $run->forceFill([
            'status' => 'success',
            'finished_at' => Carbon::now(),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'outlets_ok' => $outlets->count(),
            'outlets_failed' => 0,
            'prospects_new' => $totals->new + $secondRun['new'],
            'prospects_updated' => $totals->updated,
            'prospects_closed' => $totals->closed + $secondRun['closed'],
            'details' => array_merge($run->details ?? [], ['first_run_new' => $totals->new, 'second_run' => $secondRun, 'excluded_hitachi' => $totals->excluded]),
        ])->save();

        WhitespaceCache::bump();

        return $run;
    }

    /**
     * ~3% extra prospects on random outlets (is_baseline=false, most "new open", first seen 0-6 days ago)
     * and a handful of closures.
     *
     * @param  list<Outlet>  $outlets
     * @return array{new: int, closed: int}
     */
    private function simulateSecondRun(array $outlets, SyncContext $ctx, int $seed): array
    {
        $rng = SeededRandom::forKey('second-run', $seed);
        $target = (int) round(Prospect::query()->where('is_baseline', true)->count() * self::SECOND_RUN_NEW_RATIO);
        $extraMock = new MockProvider($seed + 1000);
        $categories = $ctx->activeCategories();
        $radius = (int) config('hunter.radius_m', 2000);

        $created = 0;
        $selected = 0;
        $placeIds = [];
        $guard = 0;
        while ($selected < $target && $guard++ < count($outlets) * 4) {
            $outlet = $outlets[$rng->int(0, count($outlets) - 1)];
            $take = min($rng->int(1, 4), $target - $selected);
            $extras = array_slice($extraMock->searchNearby(OutletPoint::fromModel($outlet), $radius, $categories)->businesses, $rng->int(0, 40), $take);
            if ($extras === []) {
                continue;
            }
            $res = $this->sync->ingestAnswers($outlet, [['provider' => 'mock', 'result' => new ProviderResult($extras, false, true, 0)]], $ctx, null, detectMissing: false);
            $created += $res->new;
            $selected += count($extras);
            foreach ($extras as $b) {
                $placeIds[] = $b->placeId;
            }
        }

        // Backdate first sightings so "new this week" has a spread and the age factor varies.
        $now = Carbon::now();
        foreach (array_chunk($placeIds, 300) as $chunk) {
            foreach (Prospect::query()->where('provider', 'mock')->whereIn('place_id', $chunk)->where('is_baseline', false)->get(['id']) as $p) {
                $seen = $now->copy()->subDays($rng->int(0, 6))->subHours($rng->int(0, 20));
                Prospect::query()->whereKey($p->id)->update(['first_seen_at' => $seen, 'last_seen_at' => $now]);
            }
        }
        // A few extras are no longer flagged "new open" (the owner already knows them).
        Prospect::query()->where('is_baseline', false)->where('is_new_open', true)->where('first_seen_at', '<', $now->copy()->subDays(5))
            ->limit(max(1, intdiv($created, 10)))->update(['is_new_open' => false]);

        $closed = $this->simulateClosures($rng, $now);

        return ['new' => $created, 'closed' => $closed];
    }

    private function simulateClosures(SeededRandom $rng, Carbon $now): int
    {
        $ids = Prospect::query()->where('lifecycle_status', LifecycleStatus::Open->value)->where('is_baseline', true)->orderBy('id')->pluck('id')->all();
        if ($ids === []) {
            return 0;
        }
        $n = max(3, (int) round(count($ids) * self::CLOSED_RATIO));
        $n = min($n, count($ids));
        $closed = 0;
        $picked = [];
        while ($closed < $n) {
            $id = $ids[$rng->int(0, count($ids) - 1)];
            if (isset($picked[$id])) {
                continue;
            }
            $picked[$id] = true;
            $permanent = $rng->chance(0.3);
            Prospect::query()->whereKey($id)->update([
                'lifecycle_status' => LifecycleStatus::Closed->value,
                'closed_at' => $now->copy()->subDays($rng->int(0, 3)),
                'closed_reason' => $permanent ? 'permanent' : 'missing_runs',
                'missed_runs' => $permanent ? 0 : 3,
                'score' => 0,
                'score_breakdown' => null,
            ]);
            $closed++;
        }

        return $closed;
    }
}
