<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportTableRequest;
use App\Repositories\ReportQuery;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function prospects(ReportTableRequest $request, ReportQuery $query): JsonResponse
    {
        $data = $request->validated();
        $page = $query->page($request->filter(), $data['sort'] ?? 'score', $data['dir'] ?? 'desc', (int) ($data['per_page'] ?? 25));

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'per_page' => $page->perPage(),
            ],
        ]);
    }
}
