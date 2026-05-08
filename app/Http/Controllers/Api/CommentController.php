<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $comments = $task->comments()->with('user')->get();

        return response()->json([
            'message'  => 'Comments fetched successfully',
            'comments' => CommentResource::collection($comments),
        ]);
    }

    public function store(StoreCommentRequest $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body'    => $request->validated()['body'],
        ]);

        $comment->load('user');

        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => new CommentResource($comment),
        ], 201);
    }

    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $comment->update($request->validated());

        return response()->json([
            'message' => 'Comment updated successfully',
            'comment' => new CommentResource($comment->load('user')),
        ]);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }
}
