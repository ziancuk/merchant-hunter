<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Sync\RunFinalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Closes a hunter:sync run once its batch has finished (dispatched from the batch's finally callback). */
class FinalizeRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $runId)
    {
        $this->onQueue('sync');
    }

    public function handle(RunFinalizer $finalizer): void
    {
        $finalizer->finalize($this->runId);
    }
}
