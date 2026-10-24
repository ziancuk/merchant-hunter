<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Repositories\PageProps;
use Illuminate\Http\JsonResponse;

class MasterCategoryController extends Controller
{
    public function index(PageProps $props): JsonResponse
    {
        return response()->json(['categories' => $props->categories()]);
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $category = Category::query()->create($data + [
            'osm_filters' => [],
            'is_active' => true,
            'sort_order' => (int) Category::query()->max('sort_order') + 1,
        ]);

        return response()->json(CategoryResource::make($category->refresh())->resolve(), 201);
    }

    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        $category->fill($request->validated())->save();

        return response()->json(CategoryResource::make($category->refresh())->resolve());
    }
}
