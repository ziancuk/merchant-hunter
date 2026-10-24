<?php

declare(strict_types=1);

namespace App\Services\Providers;

/**
 * Injectable sleeper so tests never wait. Swap it in the container:
 * app()->instance(Sleeper::class, new FakeSleeper()).
 */
class Sleeper
{
    public function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1_000_000));
        }
    }
}
