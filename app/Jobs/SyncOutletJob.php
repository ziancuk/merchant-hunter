<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Outlet;
use App\Models\SchedulerRun;
use App\Services\Sync\OutletSyncResult;
use App\Services\Sync\SyncService;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Syncs one outlet inside a hunter:sync run (TRD §6): unlimited tries bounded by retryUntil (+30 min),
 * at most 3 unhandled exceptions, backoff 30/60/120 s, never two jobs for the same outlet at once.
 * Provider failures are not exceptions: they are recorded as a `failed` scheduler_run_outlets row.
 */
class SyncOutletJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 0;

    public int $maxExceptions = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public int $outletId,
        public int $runId,
        public ?string $provider = null,
        public bool $fresh = false,
    ) {
        $this->onQueue('sync');
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function uniqueId(): string
    {
        return "sync-outlet-{$this->outletId}-run-{$this->runId}";
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("sync-outlet-{$this->outletId}"))->releaseAfter(60)->expireAfter(900)];
    }

    public function handle(SyncService $sync): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        $run = SchedulerRun::query()->find($this->runId);
        $outlet = Outlet::query()->find($this->outletId);
        if ($run === null || $outlet === null) {
            return;
        }
        if ($outlet->lat === null || $outlet->lng === null) {
            $sync->recordOutlet($run, $outlet, new OutletSyncResult(status: 'skipped', error: 'Outlet has no coordinates'));

            return;
        }

        $result = $sync->syncOutlet($outlet, null, $this->provider, $this->fresh, $run);
        $sync->recordOutlet($run, $outlet, $result);
    }

    /** Called when the job finally gives up: make sure the run shows this outlet as failed. */
    public function failed(Throwable $e): void
    {
        $run = SchedulerRun::query()->find($this->runId);
        $outlet = Outlet::query()->find($this->outletId);
        if ($run !== null && $outlet !== null) {
            app(SyncService::class)->recordOutlet($run, $outlet, new OutletSyncResult(status: 'failed', error: $e->getMessage()));
        }
    }
}
