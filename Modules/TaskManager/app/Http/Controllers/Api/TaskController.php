<?php

namespace Modules\TaskManager\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\TaskManager\Http\Requests\Task\BulkTaskRequest;
use Modules\TaskManager\Http\Requests\Task\StoreTaskRequest;
use Modules\TaskManager\Http\Requests\Task\UpdateTaskRequest;
use Modules\TaskManager\Http\Resources\TaskResource;
use Modules\TaskManager\Models\Task;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = $request->user()->tasks()->with('category', 'tags')
                ->orderByDesc('is_pinned')
                ->latest();

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
                $query->where('title', 'like', '%'.$request->search.'%');
            }

            $tasks = $query->paginate(10);

            return response()->json([
                'message' => 'Tasks fetched successfully',
                'data' => TaskResource::collection($tasks),
                'meta' => [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'total' => $tasks->total(),
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
            $task->load('category', 'tags', 'comments.user', 'attachments', 'subtasks', 'timeLogs');

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

    public function bulk(BulkTaskRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $taskIds = $request->task_ids;
            $action = $request->action;
            $value = $request->value;
            $tasks = Task::whereIn('id', $taskIds)->where('user_id', $userId)->get();

            if ($tasks->isEmpty()) {
                return response()->json(['message' => 'No matching tasks found. You can only perform bulk actions on your own tasks.'], 404);
            }

            DB::transaction(function () use ($tasks, $action, $value) {
                foreach ($tasks as $task) {
                    match ($action) {
                        'update_status' => $task->update(['status' => $value]),
                        'update_priority' => $task->update(['priority' => $value]),
                        'update_category' => $task->update(['category_id' => $value]),
                        'delete' => $task->delete(),
                    };
                }
            });

            $count = $tasks->count();

            return response()->json([
                'message' => "{$count} task(s) updated successfully.",
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to perform bulk action', ['error' => $e]);

            return response()->json(['message' => 'Something went wrong while performing the bulk action.'], 500);
        }
    }

    public function pin(Task $task): JsonResponse
    {
        try {
            $this->authorize('update', $task);

            DB::transaction(fn () => $task->update(['is_pinned' => ! $task->is_pinned]));

            $status = $task->is_pinned ? 'pinned' : 'unpinned';

            return response()->json([
                'message' => "Task {$status} successfully",
                'task' => new TaskResource($task->load('category', 'tags')),
            ]);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to pin this task.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to pin task', ['task_id' => $task->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while pinning the task.'], 500);
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
                'task' => new TaskResource($task->load('category', 'tags')),
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
