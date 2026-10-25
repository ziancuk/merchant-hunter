<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\ImportOutletsAction;
use App\Actions\ResolveOutletAction;
use App\Actions\SaveOutletAction;
use App\Enums\GeocodeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportOutletsRequest;
use App\Http\Requests\OutletIndexRequest;
use App\Http\Requests\OutletRequest;
use App\Http\Requests\ResolveOutletRequest;
use App\Http\Resources\OutletResource;
use App\Jobs\GeocodeOutletJob;
use App\Models\Outlet;
use App\Repositories\OutletRepository;
use Illuminate\Http\JsonResponse;

class MasterOutletController extends Controller
{
    public function index(OutletIndexRequest $request, OutletRepository $outlets): JsonResponse
    {
        return response()->json($outlets->paginated($request->validated()));
    }

    public function store(OutletRequest $request, SaveOutletAction $action): JsonResponse
    {
        $outlet = $action->handle($request->validated());

        return response()->json(OutletResource::make($outlet->load('province:id,name'))->resolve(), 201);
    }

    public function update(OutletRequest $request, Outlet $outlet, SaveOutletAction $action): JsonResponse
    {
        $outlet = $action->handle($request->validated(), $outlet);

        return response()->json(OutletResource::make($outlet->load('province:id,name'))->resolve());
    }

    /** Soft delete: the outlet stays for history but is no longer active. */
    public function destroy(Outlet $outlet, SaveOutletAction $action): JsonResponse
    {
        $outlet = $action->deactivate($outlet);

        return response()->json(OutletResource::make($outlet->load('province:id,name'))->resolve());
    }

    public function import(ImportOutletsRequest $request, ImportOutletsAction $action): JsonResponse
    {
        return response()->json($action->handle($request->file('file')->getRealPath()));
    }

    public function retryGeocode(Outlet $outlet): JsonResponse
    {
        $outlet->forceFill(['geocode_status' => GeocodeStatus::Ok->value, 'geocode_candidates' => null])->save();
        GeocodeOutletJob::dispatch($outlet->id);

        return response()->json(['queued' => true, 'outlet' => OutletResource::make($outlet->load('province:id,name'))->resolve()]);
    }

    public function review(OutletRepository $outlets): JsonResponse
    {
        return response()->json($outlets->reviewQueue());
    }

    public function resolve(ResolveOutletRequest $request, Outlet $outlet, ResolveOutletAction $action): JsonResponse
    {
        $data = $request->validated();
        $outlet = $action->handle(
            $outlet,
            isset($data['candidate_index']) ? (int) $data['candidate_index'] : null,
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
        );

        return response()->json(OutletResource::make($outlet->load('province:id,name'))->resolve());
    }
}
