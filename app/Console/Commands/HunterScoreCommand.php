<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Stub created in M2.0; implemented in M2.1. */
class HunterScoreCommand extends Command
{
    protected $signature = 'hunter:score {--all}';

    protected $description = 'Recompute prospect scores';

    public function handle(): int
    {
        $this->warn('Not implemented yet.');

        return self::SUCCESS;
    }
}
