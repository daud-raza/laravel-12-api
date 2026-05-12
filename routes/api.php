<?php

use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\SubtaskController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TimeLogController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'OK', 'message' => 'API is running']));

// Public routes (rate-limited to 10/min per IP)
Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Categories
    Route::apiResource('categories', CategoryController::class);

    // Tasks + restore + pin + bulk
    Route::post('/tasks/bulk',          [TaskController::class, 'bulk']);
    Route::post('/tasks/{task}/pin',    [TaskController::class, 'pin']);
    Route::post('/tasks/{id}/restore',  [TaskController::class, 'restore']);
    Route::apiResource('tasks', TaskController::class);

    // Nested: task comments
    Route::apiResource('tasks.comments', CommentController::class)
         ->only(['index', 'store', 'update', 'destroy'])
         ->shallow();

    // Nested: task attachments
    Route::get('/tasks/{task}/attachments',          [AttachmentController::class, 'index']);
    Route::post('/tasks/{task}/attachments',         [AttachmentController::class, 'store']);
    Route::delete('/tasks/{task}/attachments/{attachment}', [AttachmentController::class, 'destroy']);

    // Tags (user-owned) + sync tags on a task
    Route::apiResource('tags', TagController::class)->only(['index', 'store', 'destroy']);
    Route::post('/tasks/{task}/tags', [TagController::class, 'sync']);

    // Subtasks
    Route::get('/tasks/{task}/subtasks',              [SubtaskController::class, 'index']);
    Route::post('/tasks/{task}/subtasks',             [SubtaskController::class, 'store']);
    Route::put('/subtasks/{subtask}',                 [SubtaskController::class, 'update']);
    Route::delete('/subtasks/{subtask}',              [SubtaskController::class, 'destroy']);
    Route::patch('/subtasks/{subtask}/toggle',        [SubtaskController::class, 'toggle']);
    Route::post('/tasks/{task}/subtasks/reorder',     [SubtaskController::class, 'reorder']);

    // Time Tracking
    Route::get('/tasks/{task}/time-logs',             [TimeLogController::class, 'index']);
    Route::post('/tasks/{task}/time-logs',            [TimeLogController::class, 'store']);
    Route::patch('/time-logs/{timeLog}/stop',         [TimeLogController::class, 'stop']);
    Route::delete('/time-logs/{timeLog}',             [TimeLogController::class, 'destroy']);
});