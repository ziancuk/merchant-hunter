<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GeocodeStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\GeocodeOutletJob;
use App\Models\Outlet;

/**
 * Re-queues forward geocoding for an outlet whose geocoding failed. The status is left untouched:
 * only GeocodeOutletJob (via OutletGeocoder) moves it, so an outlet is never "ok" without coordinates.
 */
final class RetryGeocodeAction
{
    public function handle(Outlet $outlet): Outlet
    {
        if ($outlet->geocode_status !== GeocodeStatus::Failed) {
            throw new BusinessRuleException('Hanya outlet dengan status geocoding gagal yang dapat dicoba ulang.', 'geocode_not_failed');
        }

        GeocodeOutletJob::dispatch($outlet->id);

        return $outlet;
    }
}
