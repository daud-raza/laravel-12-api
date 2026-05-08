<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tag\StoreTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    // List all tags belonging to the authenticated user
    public function index(Request $request): JsonResponse
    {
        $tags = $request->user()->tags()->get();

        return response()->json([
            'message' => 'Tags fetched successfully',
            'tags'    => TagResource::collection($tags),
        ]);
    }

    // Create a new tag
    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = $request->user()->tags()->create($request->validated());

        return response()->json([
            'message' => 'Tag created successfully',
            'tag'     => new TagResource($tag),
        ], 201);
    }

    // Delete a tag
    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        $tag->delete();

        return response()->json(['message' => 'Tag deleted successfully']);
    }

    // Sync tags on a task — replaces all existing tags with the given list
    public function sync(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $userId = $request->user()->id;

        $request->validate([
            'tag_ids'   => ['required', 'array'],
            'tag_ids.*' => ['integer', "exists:tags,id,user_id,{$userId}"],
        ]);

        $task->tags()->sync($request->tag_ids);

        return response()->json([
            'message' => 'Tags synced successfully',
            'tags'    => TagResource::collection($task->tags),
        ]);
    }
}
