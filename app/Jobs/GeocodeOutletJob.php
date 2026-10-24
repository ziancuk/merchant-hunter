<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Outlet;
use App\Services\Geocoding\OutletGeocoder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Forward-geocodes one outlet (also dispatched by "retry geocode" in Master Outlet). */
class GeocodeOutletJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $outletId;

    public function __construct(int|Outlet $outlet)
    {
        $this->outletId = $outlet instanceof Outlet ? $outlet->id : $outlet;
    }

    public function handle(OutletGeocoder $geocoder): void
    {
        $outlet = Outlet::query()->find($this->outletId);
        if ($outlet === null || ! $geocoder->available()) {
            return;
        }
        $geocoder->geocode($outlet);
    }
}
