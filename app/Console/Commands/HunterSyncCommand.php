<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sync\SyncRunner;
use Illuminate\Console\Command;

class HunterSyncCommand extends Command
{
    protected $signature = 'hunter:sync {--outlet=} {--limit=} {--provider=} {--fresh}';

    protected $description = 'Nightly sync of prospects around outlets';

    public function handle(SyncRunner $runner): int
    {
        $limit = $this->option('limit');
        $run = $runner->start(
            $this->option('outlet') ?: null,
            $limit !== null && $limit !== '' ? (int) $limit : null,
            $this->option('provider') ?: null,
            (bool) $this->option('fresh'),
        );

        $this->info("Sync run #{$run->id}: {$run->outlets_total} outlet(s), status {$run->status}.");
        if ($run->status !== 'running') {
            $this->line(sprintf(
                'new %d, updated %d, closed %d, api calls %d, ok %d, failed %d',
                $run->prospects_new, $run->prospects_updated, $run->prospects_closed,
                $run->api_calls, $run->outlets_ok, $run->outlets_failed,
            ));
            if ($run->error) {
                $this->warn($run->error);
            }
        }

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
