<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Stub created in M2.0; implemented in M2.1. */
class HunterGeocodeCommand extends Command
{
    protected $signature = 'hunter:geocode';

    protected $description = 'Reverse geocode queued prospect addresses';

    public function handle(): int
    {
        $this->warn('Not implemented yet.');

        return self::SUCCESS;
    }
}
