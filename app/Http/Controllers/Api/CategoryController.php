<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $categories = $request->user()
                ->categories()
                ->withCount('tasks')
                ->latest()
                ->get();

            return response()->json([
                'message'    => 'Categories fetched successfully',  
                'categories' => CategoryResource::collection($categories),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch categories', ['error' => $e]);
            return response()->json(['message' => 'Something went wrong while fetching categories.'], 500);
        }
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            $category = DB::transaction(
                fn () => $request->user()->categories()->create($request->validated())
            );

            return response()->json([
                'message'  => 'Category created successfully',
                'category' => new CategoryResource($category),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Failed to create category', ['error' => $e]);
            return response()->json(['message' => 'Something went wrong while creating the category.'], 500);
        }
    }

    public function show(Category $category): JsonResponse
    {
        try {
            $this->authorize('view', $category);
            $category->loadCount('tasks');

            return response()->json([
                'message'  => 'Category fetched successfully',
                'category' => new CategoryResource($category),
            ]);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to view this category.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch category', ['category_id' => $category->id, 'error' => $e]);
            return response()->json(['message' => 'Something went wrong while fetching the category.'], 500);
        }
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        try {
            $this->authorize('update', $category);

            DB::transaction(fn () => $category->update($request->validated()));

            return response()->json([
                'message'  => 'Category updated successfully',
                'category' => new CategoryResource($category),
            ]);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to update this category.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to update category', ['category_id' => $category->id, 'error' => $e]);
            return response()->json(['message' => 'Something went wrong while updating the category.'], 500);
        }
    }

    public function destroy(Category $category): JsonResponse
    {
        try {
            $this->authorize('delete', $category);

            DB::transaction(fn () => $category->delete());

            return response()->json(['message' => 'Category deleted successfully']);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to delete this category.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to delete category', ['category_id' => $category->id, 'error' => $e]);
            return response()->json(['message' => 'Something went wrong while deleting the category.'], 500);
        }
    }
}
