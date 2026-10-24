<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Stub created in M2.0; implemented in M2.1. */
class HunterSeedProspectsCommand extends Command
{
    protected $signature = 'hunter:seed-prospects {--offline} {--seed=42}';

    protected $description = 'Seed prospects (offline uses MockProvider)';

    public function handle(): int
    {
        $this->warn('Not implemented yet.');

        return self::SUCCESS;
    }
}
