<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GeocodeStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Outlet;
use App\Repositories\WhiteSpaceQuery;
use App\Services\Master\ProspectReconciler;
use Illuminate\Support\Facades\Cache;

/** Resolves a needs_review outlet by choosing a geocoding candidate or entering coordinates (status manual). */
final class ResolveOutletAction
{
    public function __construct(private readonly ProspectReconciler $reconciler) {}

    public function handle(Outlet $outlet, ?int $candidateIndex = null, ?float $lat = null, ?float $lng = null): Outlet
    {
        $score = null;
        if ($candidateIndex !== null) {
            $candidate = ($outlet->geocode_candidates ?? [])[$candidateIndex] ?? null;
            if ($candidate === null) {
                throw new BusinessRuleException('Kandidat tidak ditemukan.', 'invalid_candidate');
            }
            $lat = (float) $candidate['lat'];
            $lng = (float) $candidate['lng'];
            $score = isset($candidate['score']) ? (int) $candidate['score'] : null;
        }
        if ($lat === null || $lng === null) {
            throw new BusinessRuleException('Pilih kandidat atau isi koordinat.', 'missing_coordinates');
        }

        $outlet->fill([
            'lat' => $lat, 'lng' => $lng, 'geocode_status' => GeocodeStatus::Manual->value,
            'geocode_source' => 'manual', 'geocode_score' => $score, 'geocode_candidates' => null,
        ])->save();

        $this->reconciler->reconcile([$outlet->id]);
        Cache::forever(WhiteSpaceQuery::VERSION_KEY, (int) Cache::get(WhiteSpaceQuery::VERSION_KEY, 1) + 1);

        return $outlet->refresh();
    }
}
