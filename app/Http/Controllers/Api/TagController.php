<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tag\StoreTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $tags = $request->user()->tags()->get();

            return response()->json([
                'message' => 'Tags fetched successfully',
                'tags'    => TagResource::collection($tags),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch tags', ['error' => $e]);
            return response()->json(['message' => 'Something went wrong while fetching tags.'], 500);
        }
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        try {
            $tag = DB::transaction(
                fn () => $request->user()->tags()->create($request->validated())
            );

            return response()->json([
                'message' => 'Tag created successfully',
                'tag'     => new TagResource($tag),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Failed to create tag', ['error' => $e]);
            return response()->json(['message' => 'Something went wrong while creating the tag.'], 500);
        }
    }

    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        try {
            $this->authorize('delete', $tag);

            DB::transaction(fn () => $tag->delete());

            return response()->json(['message' => 'Tag deleted successfully']);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to delete this tag.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to delete tag', ['tag_id' => $tag->id, 'error' => $e]);
            return response()->json(['message' => 'Something went wrong while deleting the tag.'], 500);
        }
    }

    public function sync(Request $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('update', $task);

            $userId = $request->user()->id;

            $request->validate([
                'tag_ids'   => ['required', 'array'],
                'tag_ids.*' => ['integer', "exists:tags,id,user_id,{$userId}"],
            ]);

            DB::transaction(fn () => $task->tags()->sync($request->tag_ids));

            return response()->json([
                'message' => 'Tags synced successfully',
                'tags'    => TagResource::collection($task->tags()->get()),
            ]);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to update tags on this task.'], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'One or more tag IDs are invalid. Make sure you only use tags that belong to your account.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to sync tags', ['task_id' => $task->id, 'error' => $e]);
            return response()->json(['message' => 'Something went wrong while syncing tags.'], 500);
        }
    }
}
