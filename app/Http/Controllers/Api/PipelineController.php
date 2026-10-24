<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PipelineCardsRequest;
use App\Http\Requests\PipelineTableRequest;
use App\Http\Resources\CardResource;
use App\Repositories\PipelineQuery;
use Illuminate\Http\JsonResponse;

class PipelineController extends Controller
{
    public function cards(PipelineCardsRequest $request, PipelineQuery $query): JsonResponse
    {
        $data = $request->validated();

        return response()->json(['columns' => $query->columns(
            $request->filter(),
            (int) ($data['per_status'] ?? 50),
            $data['status'] ?? null,
            $data['cursor'] ?? null,
        )]);
    }

    public function table(PipelineTableRequest $request, PipelineQuery $query): JsonResponse
    {
        $data = $request->validated();
        $page = $query->table($request->filter(), $data['sort'] ?? 'score', $data['dir'] ?? 'desc', (int) ($data['per_page'] ?? 25));

        return response()->json([
            'data' => CardResource::collection($page->items())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'per_page' => $page->perPage(),
            ],
        ]);
    }
}
