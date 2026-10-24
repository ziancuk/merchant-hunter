<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Services\Providers\Sleeper;
use Illuminate\Support\Carbon;

/** Records requested sleeps and advances the frozen clock instead of waiting. */
class FakeSleeper extends Sleeper
{
    /** @var list<float> */
    public array $sleeps = [];

    public function sleep(float $seconds): void
    {
        $this->sleeps[] = $seconds;
        if ($seconds > 0 && Carbon::hasTestNow()) {
            Carbon::setTestNow(Carbon::now()->addMilliseconds((int) round($seconds * 1000)));
        }
    }

    public static function install(): self
    {
        $s = new self;
        app()->instance(Sleeper::class, $s);

        return $s;
    }
}
