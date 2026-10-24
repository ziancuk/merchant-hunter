<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sync\OfflineSeeder;
use Illuminate\Console\Command;

class HunterSeedProspectsCommand extends Command
{
    protected $signature = 'hunter:seed-prospects {--offline} {--seed=42}';

    protected $description = 'Seed prospects (offline uses MockProvider)';

    public function handle(OfflineSeeder $seeder): int
    {
        if (! $this->option('offline')) {
            // Real pipeline: same as the nightly run, using the active data sources.
            return $this->call('hunter:sync');
        }

        $seed = (int) $this->option('seed');
        $this->info("Seeding simulated prospects offline (seed {$seed})...");
        $started = microtime(true);
        $run = $seeder->seed($seed, fn (string $m) => $this->line($m));

        if ($run === null) {
            $this->warn('No active outlet with coordinates; seed outlets first (migrate:fresh --seed).');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Run #%d %s: %d outlet(s), %d new prospect(s), %d closed, in %.1f s.',
            $run->id, $run->status, $run->outlets_total, $run->prospects_new, $run->prospects_closed, microtime(true) - $started,
        ));

        return self::SUCCESS;
    }
}
