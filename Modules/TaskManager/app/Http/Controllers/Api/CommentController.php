<?php

namespace Modules\TaskManager\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\TaskManager\Http\Requests\Comment\StoreCommentRequest;
use Modules\TaskManager\Http\Requests\Comment\UpdateCommentRequest;
use Modules\TaskManager\Http\Resources\CommentResource;
use Modules\TaskManager\Models\Comment;
use Modules\TaskManager\Models\Task;

class CommentController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        try {
            $this->authorize('view', $task);

            $comments = $task->comments()->with('user')->get();

            return response()->json([
                'message' => 'Comments fetched successfully',
                'comments' => CommentResource::collection($comments),
            ]);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to view comments on this task.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch comments', ['task_id' => $task->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while fetching comments.'], 500);
        }
    }

    public function store(StoreCommentRequest $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('view', $task);

            $comment = DB::transaction(function () use ($request, $task) {
                $comment = $task->comments()->create([
                    'user_id' => $request->user()->id,
                    'body' => $request->validated()['body'],
                ]);
                $comment->load('user');

                return $comment;
            });

            return response()->json([
                'message' => 'Comment added successfully',
                'comment' => new CommentResource($comment),
            ], 201);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to comment on this task.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to add comment', ['task_id' => $task->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while adding the comment.'], 500);
        }
    }

    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        try {
            $this->authorize('update', $comment);

            DB::transaction(fn () => $comment->update($request->validated()));

            return response()->json([
                'message' => 'Comment updated successfully',
                'comment' => new CommentResource($comment->load('user')),
            ]);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to update this comment.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to update comment', ['comment_id' => $comment->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while updating the comment.'], 500);
        }
    }

    public function destroy(Comment $comment): JsonResponse
    {
        try {
            $this->authorize('delete', $comment);

            DB::transaction(fn () => $comment->delete());

            return response()->json(['message' => 'Comment deleted successfully']);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to delete this comment.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to delete comment', ['comment_id' => $comment->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while deleting the comment.'], 500);
        }
    }
}
