<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = $request->user()
            ->categories()
            ->withCount('tasks')
            ->latest()
            ->get();

        return response()->json([
            'message'    => 'Categories fetched successfully',
            'categories' => CategoryResource::collection($categories),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $request->user()->categories()->create($request->validated());

        return response()->json([
            'message'  => 'Category created successfully',
            'category' => new CategoryResource($category),
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);
        $category->loadCount('tasks');

        return response()->json([
            'message'  => 'Category fetched successfully',
            'category' => new CategoryResource($category),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);
        $category->update($request->validated());

        return response()->json([
            'message'  => 'Category updated successfully',
            'category' => new CategoryResource($category),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}
