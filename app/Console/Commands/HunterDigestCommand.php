<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\GenerateDigestAction;
use App\Models\SchedulerRun;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class HunterDigestCommand extends Command
{
    protected $signature = 'hunter:digest {--date= : Digest date (YYYY-MM-DD), default today} {--force : Rebuild an existing digest}';

    protected $description = 'Build the daily digest (new open, hot, follow-up); idempotent per date';

    public function handle(GenerateDigestAction $action): int
    {
        $date = $this->option('date') ? Carbon::parse((string) $this->option('date')) : now();
        $run = SchedulerRun::query()->create(['command' => 'hunter:digest', 'status' => 'running', 'started_at' => now()]);

        try {
            ['digest' => $digest, 'created' => $created] = $action->handle($date, (bool) $this->option('force'));
        } catch (Throwable $e) {
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error' => $e->getMessage()]);
            $this->error('Digest failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $run->update([
            'status' => 'success',
            'finished_at' => now(),
            'duration_ms' => (int) max(0, $run->started_at->diffInMilliseconds(now(), true)),
            'details' => ['date' => $date->format('Y-m-d'), 'created' => $created],
        ]);

        $p = $digest->payload;
        $this->info(sprintf(
            'Digest %s %s: %d baru buka, %d panas, %d follow-up.',
            $date->format('Y-m-d'),
            $created ? 'dibuat' : 'sudah ada',
            count($p['new_open'] ?? []),
            count($p['hot'] ?? []),
            count($p['follow_up'] ?? []),
        ));

        return self::SUCCESS;
    }
}
