<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MapOutletsRequest;
use App\Http\Requests\MapProspectsRequest;
use App\Http\Requests\SearchRequest;
use App\Http\Requests\WhitespaceRequest;
use App\Repositories\MapQuery;
use App\Repositories\SearchQuery;
use App\Repositories\WhiteSpaceQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function outlets(MapOutletsRequest $request, MapQuery $map): JsonResponse
    {
        $province = $request->validated()['province_id'] ?? null;

        return $this->cached($request, $map->outlets($province !== null ? (int) $province : null));
    }

    public function prospects(MapProspectsRequest $request, MapQuery $map): JsonResponse
    {
        return $this->cached($request, $map->prospects($request->bbox(), (int) $request->validated()['zoom'], $request->filter()));
    }

    public function whitespace(WhitespaceRequest $request, WhiteSpaceQuery $whiteSpace): JsonResponse
    {
        $cells = array_map(
            fn (array $c) => array_diff_key($c, ['province_id' => 1]),
            $whiteSpace->cells($request->bbox(), $request->categoryIds()),
        );

        return $this->cached($request, ['cell_size_m' => 1000, 'cells' => $cells]);
    }

    public function search(SearchRequest $request, SearchQuery $search): JsonResponse
    {
        return response()->json($search->search($request->validated()['q']));
    }

    /** JSON with a strong ETag and a 30 s private cache (TRD §8); 304 when unchanged. */
    private function cached(Request $request, array $payload): JsonResponse
    {
        $response = response()->json($payload);
        $response->setEtag(md5((string) $response->getContent()));
        $response->headers->set('Cache-Control', 'private, max-age=30');
        $response->isNotModified($request);

        return $response;
    }
}
