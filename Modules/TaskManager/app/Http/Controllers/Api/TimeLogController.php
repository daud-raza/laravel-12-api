<?php

namespace Modules\TaskManager\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\TaskManager\Http\Requests\TimeLog\StoreTimeLogRequest;
use Modules\TaskManager\Http\Resources\TimeLogResource;
use Modules\TaskManager\Models\Task;
use Modules\TaskManager\Models\TimeLog;

class TimeLogController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        try {
            $this->authorize('viewAny', [TimeLog::class, $task]);

            $logs = $task->timeLogs()->latest()->get();

            return response()->json([
                'message' => 'Time logs fetched successfully',
                'data' => TimeLogResource::collection($logs),
                'total_minutes' => $logs->sum('duration_minutes'),
            ]);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to view time logs for this task.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch time logs', ['task_id' => $task->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while fetching time logs.'], 500);
        }
    }

    public function store(StoreTimeLogRequest $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('create', [TimeLog::class, $task]);

            $log = DB::transaction(function () use ($request, $task) {
                $startedAt = $request->filled('started_at')
                    ? $request->started_at
                    : now();

                $endedAt = $request->filled('ended_at') ? $request->ended_at : null;
                $durationMinutes = null;

                if ($endedAt) {
                    $durationMinutes = (int) round(
                        Carbon::parse($startedAt)->diffInMinutes(Carbon::parse($endedAt))
                    );
                }

                return $task->timeLogs()->create([
                    'user_id' => $request->user()->id,
                    'started_at' => $startedAt,
                    'ended_at' => $endedAt,
                    'duration_minutes' => $durationMinutes,
                    'note' => $request->note,
                ]);
            });

            $message = $log->ended_at ? 'Time log created successfully' : 'Timer started successfully';

            return response()->json([
                'message' => $message,
                'time_log' => new TimeLogResource($log),
            ], 201);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to log time for this task.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to create time log', ['task_id' => $task->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while creating the time log.'], 500);
        }
    }

    public function stop(TimeLog $timeLog): JsonResponse
    {
        try {
            $this->authorize('update', $timeLog);

            if ($timeLog->ended_at) {
                return response()->json(['message' => 'This timer has already been stopped.'], 422);
            }

            DB::transaction(function () use ($timeLog) {
                $endedAt = now();
                $durationMinutes = (int) round($timeLog->started_at->diffInMinutes($endedAt));

                $timeLog->update([
                    'ended_at' => $endedAt,
                    'duration_minutes' => $durationMinutes,
                ]);
            });

            return response()->json([
                'message' => 'Timer stopped successfully',
                'time_log' => new TimeLogResource($timeLog),
            ]);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to stop this timer.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to stop timer', ['time_log_id' => $timeLog->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while stopping the timer.'], 500);
        }
    }

    public function destroy(TimeLog $timeLog): JsonResponse
    {
        try {
            $this->authorize('delete', $timeLog);

            DB::transaction(fn () => $timeLog->delete());

            return response()->json(['message' => 'Time log deleted successfully']);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to delete this time log.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to delete time log', ['time_log_id' => $timeLog->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while deleting the time log.'], 500);
        }
    }
}
