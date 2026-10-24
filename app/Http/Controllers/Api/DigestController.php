<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DigestRequest;
use App\Repositories\DashboardQuery;
use Illuminate\Http\JsonResponse;

class DigestController extends Controller
{
    public function show(DigestRequest $request, DashboardQuery $dashboard): JsonResponse
    {
        return response()->json($dashboard->digest($request->validated()['date'] ?? null));
    }
}
