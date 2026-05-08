<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse {
        $query = $request->user()->tasks()->with('category')->latest();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search by title
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $tasks = $query->paginate(10);

        return response()->json([
            'message' => 'Tasks fetched successfully',
            'tasks'   => $tasks,
        ]);
    }

    public function store(StoreTaskRequest $request): JsonResponse {
        $task = $request->user()->tasks()->create($request->validated());

        $task->load('category');

        return response()->json([
            'message' => 'Task created successfully',
            'task'    => $task,
        ], 201);
    }

    public function show(Request $request, Task $task): JsonResponse {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $task->load('category');

        return response()->json([
            'message' => 'Task fetched successfully',
            'task'    => $task,
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $task->update($request->validated());

        $task->load('category');

        return response()->json([
            'message' => 'Task updated successfully',
            'task'    => $task,
        ]);
    }

    public function destroy(Request $request, Task $task): JsonResponse {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $task->delete();

        return response()->json([
            'message' => 'Task deleted successfully',
        ]);
    }
}
