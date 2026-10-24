<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Stub created in M2.0; implemented in M2.1. */
class HunterSyncCommand extends Command
{
    protected $signature = 'hunter:sync {--outlet=} {--limit=} {--provider=} {--fresh}';

    protected $description = 'Nightly sync of prospects around outlets';

    public function handle(): int
    {
        $this->warn('Not implemented yet.');

        return self::SUCCESS;
    }
}
