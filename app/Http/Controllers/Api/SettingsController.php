<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImpactRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Services\Impact\ImpactCalculator;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function show(SettingsService $settings): JsonResponse
    {
        return response()->json($settings->structured());
    }

    public function update(UpdateSettingsRequest $request, SettingsService $settings): JsonResponse
    {
        return response()->json($settings->updateStructured($request->settings()));
    }

    public function impact(ImpactRequest $request, SettingsService $settings, ImpactCalculator $calculator): JsonResponse
    {
        $assumptions = $settings->saveImpactAssumptions($request->validated());

        return response()->json(['assumptions' => $assumptions, 'results' => $calculator->calculate($assumptions)]);
    }
}
