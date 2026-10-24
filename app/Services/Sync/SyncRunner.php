<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Jobs\FinalizeRunJob;
use App\Jobs\SyncOutletJob;
use App\Models\Outlet;
use App\Models\SchedulerRun;
use App\Models\Setting;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

/** Starts a `hunter:sync` run: picks outlets, creates scheduler_runs and dispatches the batch (TRD §6). */
class SyncRunner
{
    public const COMMAND = 'hunter:sync';

    public function __construct(private readonly RunFinalizer $finalizer) {}

    /** Active, located outlets (or one explicit outlet), least recently synced first, capped. */
    public function selectOutlets(?string $outletRef = null, ?int $limit = null): Collection
    {
        $query = Outlet::query()->located();
        if ($outletRef !== null && $outletRef !== '') {
            $query->where(fn ($q) => $q->where('code', $outletRef)->orWhere('id', ctype_digit($outletRef) ? (int) $outletRef : 0));
        } else {
            $query->active();
        }
        $cap = (int) Setting::read('sync.max_outlets_per_run', config('hunter.settings_defaults.sync.max_outlets_per_run', 200));
        $cap = max(1, $limit !== null && $limit > 0 ? min($limit, $cap) : $cap);

        return $query
            ->orderByRaw('CASE WHEN last_synced_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('last_synced_at')
            ->orderBy('id')
            ->limit($cap)
            ->get();
    }

    public function start(?string $outletRef = null, ?int $limit = null, ?string $provider = null, bool $fresh = false): SchedulerRun
    {
        $outlets = $this->selectOutlets($outletRef, $limit);
        $run = SchedulerRun::query()->create([
            'command' => self::COMMAND,
            'status' => 'running',
            'started_at' => Carbon::now(),
            'outlets_total' => $outlets->count(),
            'outlets_ok' => 0, 'outlets_failed' => 0,
            'prospects_new' => 0, 'prospects_updated' => 0, 'prospects_closed' => 0,
            'api_calls' => 0,
            'details' => ['provider' => $provider, 'fresh' => $fresh, 'outlet' => $outletRef],
        ]);

        if ($outlets->isEmpty()) {
            $this->finalizer->finalize($run->id);

            return $run->refresh();
        }

        $jobs = $outlets->map(fn (Outlet $o) => new SyncOutletJob($o->id, $run->id, $provider, $fresh))->all();
        $runId = $run->id;
        Bus::batch($jobs)
            ->name("hunter:sync #{$runId}")
            ->onQueue('sync')
            ->allowFailures()
            ->finally(function (Batch $batch) use ($runId) {
                FinalizeRunJob::dispatch($runId);
            })
            ->dispatch();

        return $run->refresh();
    }
}
