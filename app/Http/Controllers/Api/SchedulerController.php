<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PageRequest;
use App\Http\Resources\SchedulerRunResource;
use App\Models\SchedulerRun;
use App\Repositories\PageProps;
use Illuminate\Http\JsonResponse;

class SchedulerController extends Controller
{
    public function index(PageRequest $request, PageProps $props): JsonResponse
    {
        return response()->json($props->schedulerRuns());
    }

    public function show(SchedulerRun $run): JsonResponse
    {
        return response()->json(SchedulerRunResource::make($run->load('outlets.outlet:id,name'))->resolve());
    }
}
