<?php

namespace App\Observers;

use App\Events\TaskCompleted;
use App\Models\Task;

class TaskObserver
{
    public function updated(Task $task): void
    {
        // When status changes to completed, stamp completed_at and fire event
        if ($task->wasChanged('status') && $task->status === 'completed') {
            $task->timestamps = false;
            $task->completed_at = now();
            $task->saveQuietly(); // avoids re-triggering this observer

            TaskCompleted::dispatch($task);
        }

        // When status changes away from completed, clear the timestamp
        if ($task->wasChanged('status') && $task->status !== 'completed') {
            $task->timestamps = false;
            $task->completed_at = null;
            $task->saveQuietly();
        }
    }

    public function created(Task $task): void {}
    public function deleted(Task $task): void {}
    public function restored(Task $task): void {}
    public function forceDeleted(Task $task): void {}
}
