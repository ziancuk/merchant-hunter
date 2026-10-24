<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoutePlanRequest;
use App\Models\Prospect;
use App\Services\Routing\RoutePlanner;
use Illuminate\Http\JsonResponse;

class RouteController extends Controller
{
    public function plan(RoutePlanRequest $request, RoutePlanner $planner): JsonResponse
    {
        $data = $request->validated();
        $ids = array_map('intval', $data['prospect_ids']);

        $found = Prospect::query()->whereIn('id', $ids)->whereNotNull('lat')->whereNotNull('lng')
            ->get(['id', 'name', 'lat', 'lng'])->keyBy('id');

        $stops = [];
        foreach ($ids as $id) {
            if ($p = $found->get($id)) {
                $stops[] = ['id' => $p->id, 'name' => $p->name, 'lat' => (float) $p->lat, 'lng' => (float) $p->lng];
            }
        }
        if (count($stops) < 2) {
            throw new BusinessRuleException(
                'Minimal 2 prospek dengan koordinat diperlukan untuk menyusun rute.',
                'not_enough_stops',
                422,
                ['prospect_ids' => ['Minimal 2 prospek dengan koordinat diperlukan.']],
            );
        }

        $start = isset($data['start']) ? ['lat' => (float) $data['start']['lat'], 'lng' => (float) $data['start']['lng']] : null;

        return response()->json($planner->plan($stops, $start));
    }
}
