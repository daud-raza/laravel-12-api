<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = $request->user()->tasks()->with('category', 'tags')->latest();

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }
            if ($request->filled('search')) {
                $query->where('title', 'like', '%' . $request->search . '%');
            }

            $tasks = $query->paginate(10);

            return response()->json([
                'message' => 'Tasks fetched successfully',
                'data'    => TaskResource::collection($tasks),
                'meta'    => [
                    'current_page' => $tasks->currentPage(),
                    'last_page'    => $tasks->lastPage(),
                    'total'        => $tasks->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch tasks', ['error' => $e]);
            return response()->json(['message' => 'Something went wrong while fetching tasks.'], 500);
        }
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        try {
            $task = DB::transaction(function () use ($request) {
                $task = $request->user()->tasks()->create($request->validated());
                $task->load('category', 'tags');
                return $task;
            });

            return response()->json(new TaskResource($task), 201);
        } catch (\Throwable $e) {
            Log::error('Failed to create task', ['error' => $e]);
            return response()->json(['message' => 'Something went wrong while creating the task.'], 500);
        }
    }

    public function show(Task $task): JsonResponse
    {
        try {
            $this->authorize('view', $task);
            $task->load('category', 'tags', 'comments.user', 'attachments');

            return response()->json(new TaskResource($task));
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to view this task.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch task', ['task_id' => $task->id, 'error' => $e]);
            return response()->json(['message' => 'Something went wrong while fetching the task.'], 500);
        }
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('update', $task);

            $task = DB::transaction(function () use ($request, $task) {
                $task->update($request->validated());
                $task->load('category', 'tags');
                return $task;
            });

            return response()->json(new TaskResource($task));
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to update this task.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to update task', ['task_id' => $task->id, 'error' => $e]);
            return response()->json(['message' => 'Something went wrong while updating the task.'], 500);
        }
    }

    public function destroy(Task $task): JsonResponse
    {
        try {
            $this->authorize('delete', $task);

            DB::transaction(fn () => $task->delete());

            return response()->json(['message' => 'Task deleted successfully']);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to delete this task.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to delete task', ['task_id' => $task->id, 'error' => $e]);
            return response()->json(['message' => 'Something went wrong while deleting the task.'], 500);
        }
    }

    public function restore(int $id): JsonResponse
    {
        try {
            $task = Task::withTrashed()->findOrFail($id);
            $this->authorize('restore', $task);

            DB::transaction(fn () => $task->restore());

            return response()->json([
                'message' => 'Task restored successfully',
                'task'    => new TaskResource($task->load('category', 'tags')),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => "No task found with ID {$id}. It may have been permanently deleted."], 404);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to restore this task.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to restore task', ['task_id' => $id, 'error' => $e]);
            return response()->json(['message' => 'Something went wrong while restoring the task.'], 500);
        }
    }
}
