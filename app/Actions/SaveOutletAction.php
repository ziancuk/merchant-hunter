<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GeocodeStatus;
use App\Jobs\GeocodeOutletJob;
use App\Models\Outlet;
use App\Repositories\WhiteSpaceQuery;
use App\Services\Master\ProspectReconciler;
use Illuminate\Support\Facades\Cache;

/** Create/update one outlet from the master form; missing coordinates queue a geocoding job. */
final class SaveOutletAction
{
    public function __construct(private readonly ProspectReconciler $reconciler) {}

    /** @param array<string, mixed> $data validated */
    public function handle(array $data, ?Outlet $outlet = null): Outlet
    {
        $creating = $outlet === null;
        $outlet ??= new Outlet(['is_simulated' => false, 'geocode_status' => GeocodeStatus::Ok->value]);

        $hasCoords = isset($data['lat'], $data['lng']);
        $outlet->fill(array_intersect_key($data, array_flip(['code', 'name', 'address', 'city', 'province_id', 'is_active'])));

        if ($hasCoords) {
            $outlet->fill([
                'lat' => (float) $data['lat'], 'lng' => (float) $data['lng'], 'geocode_source' => 'manual',
                'geocode_status' => GeocodeStatus::Manual->value, 'geocode_score' => null, 'geocode_candidates' => null,
            ]);
        }
        $outlet->save();

        if ($hasCoords) {
            $this->reconciler->reconcile([$outlet->id]);
            Cache::forever(WhiteSpaceQuery::VERSION_KEY, (int) Cache::get(WhiteSpaceQuery::VERSION_KEY, 1) + 1);
        } elseif ($outlet->lat === null || $outlet->lng === null) {
            GeocodeOutletJob::dispatch($outlet->id);
        }

        return $creating ? $outlet->refresh() : $outlet;
    }
}
