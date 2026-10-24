<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Stub created in M2.0; implemented in M2.1. */
class HunterDemoResetCommand extends Command
{
    protected $signature = 'hunter:demo-reset';

    protected $description = 'Reset demo data to the deterministic seed';

    public function handle(): int
    {
        $this->warn('Not implemented yet.');

        return self::SUCCESS;
    }
}
