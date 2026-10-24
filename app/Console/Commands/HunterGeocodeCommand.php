<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Geocoding\ReverseGeocodeService;
use Illuminate\Console\Command;

class HunterGeocodeCommand extends Command
{
    protected $signature = 'hunter:geocode {--max= : Maximum items this run (default hunter.geocoding.max_per_run)}';

    protected $description = 'Reverse geocode queued prospect addresses';

    public function handle(ReverseGeocodeService $service): int
    {
        if (! (bool) config('hunter.geocoding.enabled', true)) {
            $this->warn('Geocoding is disabled (GEOCODING_ENABLED=false).');

            return self::SUCCESS;
        }
        if (! $service->available()) {
            $this->warn('No geocoder is configured; nothing to do.');

            return self::SUCCESS;
        }
        if ($service->pendingCount() === 0) {
            $this->info('Geocode queue is empty.');

            return self::SUCCESS;
        }

        $max = $this->option('max') !== null ? (int) $this->option('max') : (int) config('hunter.geocoding.max_per_run', 100);
        $started = microtime(true);
        $stats = $service->run(max(1, $max));
        $run = $service->recordRun($stats, (int) round((microtime(true) - $started) * 1000));

        $this->info(sprintf(
            'Geocode run #%d: %d processed, %d done, %d retry, %d failed%s.',
            $run->id, $stats['processed'], $stats['done'], $stats['retry'], $stats['failed'],
            $stats['stopped'] !== null ? ', stopped ('.$stats['stopped'].')' : '',
        ));

        return self::SUCCESS;
    }
}
