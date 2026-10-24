<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Services\Geocoding\GeocodingException;
use App\Services\Geocoding\OutletGeocoder;
use Illuminate\Console\Command;

class HunterGeocodeOutletsCommand extends Command
{
    protected $signature = 'hunter:geocode-outlets {--limit= : Maximum outlets this run}';

    protected $description = 'Forward geocode outlets without coordinates';

    public function handle(OutletGeocoder $geocoder): int
    {
        if (! $geocoder->available()) {
            $this->warn('Geocoding is disabled or no geocoder is configured.');

            return self::SUCCESS;
        }

        // Failed / manual / awaiting-review outlets are retried from the UI, not here.
        $outlets = Outlet::query()
            ->where(fn ($q) => $q->whereNull('lat')->orWhereNull('lng'))
            ->whereNotIn('geocode_status', ['manual', 'failed', 'needs_review'])
            ->orderBy('id')
            ->when($this->option('limit'), fn ($q, $n) => $q->limit((int) $n))
            ->get();

        $counts = ['ok' => 0, 'needs_review' => 0, 'failed' => 0];
        foreach ($outlets as $outlet) {
            try {
                $counts[$geocoder->geocode($outlet)]++;
            } catch (GeocodingException $e) {
                $this->warn('Stopped: '.$e->getMessage());

                break;
            }
        }

        $this->info(sprintf('%d outlet(s): %d placed, %d to review, %d failed.', $outlets->count(), $counts['ok'], $counts['needs_review'], $counts['failed']));

        return self::SUCCESS;
    }
}
