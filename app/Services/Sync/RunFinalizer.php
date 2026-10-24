<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\SchedulerRun;
use App\Models\SchedulerRunOutlet;
use Illuminate\Support\Carbon;

/** Closes a `hunter:sync` run: totals, status success/partial/failed, duration and cache invalidation. */
class RunFinalizer
{
    public function finalize(int $runId): ?SchedulerRun
    {
        $run = SchedulerRun::query()->find($runId);
        if ($run === null || $run->status !== 'running') {
            return $run;
        }

        $rows = SchedulerRunOutlet::query()->where('scheduler_run_id', $run->id)->get();
        $ok = $rows->whereIn('status', ['ok', 'cached', 'skipped'])->count();
        $failedRows = $rows->where('status', 'failed');
        // Outlets that never wrote a row (job died) count as failed as well.
        $failed = max($failedRows->count(), $run->outlets_total - $ok);

        $status = match (true) {
            $failed === 0 => 'success',
            $ok === 0 => 'failed',
            default => 'partial',
        };

        $errors = $failedRows->take(5)->map(fn (SchedulerRunOutlet $r) => '#'.$r->outlet_id.': '.($r->error ?? 'failed'))->implode("\n");
        $now = Carbon::now();
        $details = $run->details ?? [];
        $details['providers'] = $rows->groupBy('provider')->map->count()->all();
        $details['cached_outlets'] = $rows->where('status', 'cached')->count();

        $run->forceFill([
            'status' => $status,
            'finished_at' => $now,
            'duration_ms' => (int) max(0, $run->started_at !== null ? $run->started_at->diffInMilliseconds($now, false) : 0),
            'outlets_ok' => $ok,
            'outlets_failed' => $failed,
            'prospects_new' => (int) $rows->sum('new_count'),
            'prospects_updated' => (int) $rows->sum('updated_count'),
            'prospects_closed' => (int) $rows->sum('closed_count'),
            'error' => $errors !== '' ? $errors : null,
            'details' => $details,
        ])->save();

        WhitespaceCache::bump();

        return $run;
    }
}
