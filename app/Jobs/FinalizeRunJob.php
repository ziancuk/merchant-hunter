<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Stub created in M2.0; implemented by BE-Provider-Scheduler. */
class FinalizeRunJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        // Implemented in M2.1.
    }
}
