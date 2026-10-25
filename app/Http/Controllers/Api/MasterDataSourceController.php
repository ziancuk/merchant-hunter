<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateDataSourceAction;
use App\DTO\ConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDataSourceRequest;
use App\Http\Resources\DataSourceResource;
use App\Models\DataSource;
use App\Repositories\PageProps;
use App\Services\Master\ConnectionTester;
use Illuminate\Http\JsonResponse;
use Throwable;

class MasterDataSourceController extends Controller
{
    public function index(PageProps $props): JsonResponse
    {
        return response()->json(['sources' => $props->dataSources()]);
    }

    public function update(UpdateDataSourceRequest $request, DataSource $dataSource, UpdateDataSourceAction $action): JsonResponse
    {
        return response()->json(DataSourceResource::make($action->handle($dataSource, $request->validated()))->resolve());
    }

    public function test(DataSource $dataSource, ConnectionTester $tester): JsonResponse
    {
        $started = hrtime(true);
        try {
            $status = $tester->test($dataSource);
        } catch (Throwable) {
            // Never echo exception text: it could contain the request URL with the key.
            $status = new ConnectionStatus(false, __('api.connection.failed'));
        }

        return response()->json([
            'ok' => $status->ok,
            'message' => $status->message,
            'latency_ms' => $status->latencyMs ?? (int) round((hrtime(true) - $started) / 1_000_000),
        ]);
    }
}
