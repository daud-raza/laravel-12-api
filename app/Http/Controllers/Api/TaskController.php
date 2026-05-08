<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
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
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $request->user()->tasks()->create($request->validated());
        $task->load('category', 'tags');

        return response()->json(new TaskResource($task), 201);
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);
        $task->load('category', 'tags', 'comments.user', 'attachments');

        return response()->json(new TaskResource($task));
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $task->update($request->validated());
        $task->load('category', 'tags');

        return response()->json(new TaskResource($task));
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);
        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }

    public function restore(int $id): JsonResponse
    {
        $task = Task::withTrashed()->findOrFail($id);
        $this->authorize('restore', $task);
        $task->restore();

        return response()->json([
            'message' => 'Task restored successfully',
            'task'    => new TaskResource($task->load('category', 'tags')),
        ]);
    }
}
