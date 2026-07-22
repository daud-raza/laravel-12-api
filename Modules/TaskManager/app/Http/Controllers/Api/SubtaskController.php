<?php

namespace Modules\TaskManager\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\TaskManager\Http\Requests\Subtask\StoreSubtaskRequest;
use Modules\TaskManager\Http\Requests\Subtask\UpdateSubtaskRequest;
use Modules\TaskManager\Http\Resources\SubtaskResource;
use Modules\TaskManager\Models\Subtask;
use Modules\TaskManager\Models\Task;

class SubtaskController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        try {
            $this->authorize('viewAny', [Subtask::class, $task]);

            $subtasks = $task->subtasks()->orderBy('order')->orderBy('id')->get();

            return response()->json([
                'message' => 'Subtasks fetched successfully',
                'data' => SubtaskResource::collection($subtasks),
                'meta' => [
                    'total' => $subtasks->count(),
                    'completed' => $subtasks->where('is_completed', true)->count(),
                ],
            ]);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to view subtasks for this task.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch subtasks', ['task_id' => $task->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while fetching subtasks.'], 500);
        }
    }

    public function store(StoreSubtaskRequest $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('create', [Subtask::class, $task]);

            $subtask = DB::transaction(function () use ($request, $task) {
                $maxOrder = $task->subtasks()->max('order') ?? -1;

                return $task->subtasks()->create([
                    'title' => $request->title,
                    'order' => $request->input('order', $maxOrder + 1),
                ]);
            });

            return response()->json([
                'message' => 'Subtask created successfully',
                'subtask' => new SubtaskResource($subtask),
            ], 201);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to add subtasks to this task.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to create subtask', ['task_id' => $task->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while creating the subtask.'], 500);
        }
    }

    public function update(UpdateSubtaskRequest $request, Subtask $subtask): JsonResponse
    {
        try {
            $this->authorize('update', $subtask);

            DB::transaction(fn () => $subtask->update($request->validated()));

            return response()->json([
                'message' => 'Subtask updated successfully',
                'subtask' => new SubtaskResource($subtask),
            ]);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to update this subtask.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to update subtask', ['subtask_id' => $subtask->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while updating the subtask.'], 500);
        }
    }

    public function destroy(Subtask $subtask): JsonResponse
    {
        try {
            $this->authorize('delete', $subtask);

            DB::transaction(fn () => $subtask->delete());

            return response()->json(['message' => 'Subtask deleted successfully']);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to delete this subtask.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to delete subtask', ['subtask_id' => $subtask->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while deleting the subtask.'], 500);
        }
    }

    public function toggle(Subtask $subtask): JsonResponse
    {
        try {
            $this->authorize('update', $subtask);

            DB::transaction(fn () => $subtask->update(['is_completed' => ! $subtask->is_completed]));

            $status = $subtask->is_completed ? 'completed' : 'uncompleted';

            return response()->json([
                'message' => "Subtask marked as {$status}",
                'subtask' => new SubtaskResource($subtask),
            ]);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to update this subtask.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle subtask', ['subtask_id' => $subtask->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while toggling the subtask.'], 500);
        }
    }

    public function reorder(Request $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('create', [Subtask::class, $task]);

            $request->validate([
                'subtask_ids' => ['required', 'array', 'min:1'],
                'subtask_ids.*' => ['integer', 'exists:subtasks,id'],
            ]);

            DB::transaction(function () use ($request, $task) {
                foreach ($request->subtask_ids as $index => $subtaskId) {
                    $task->subtasks()->where('id', $subtaskId)->update(['order' => $index]);
                }
            });

            return response()->json(['message' => 'Subtasks reordered successfully']);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to reorder subtasks for this task.'], 403);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to reorder subtasks', ['task_id' => $task->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while reordering subtasks.'], 500);
        }
    }
}
