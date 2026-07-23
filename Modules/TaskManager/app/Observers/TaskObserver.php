<?php

namespace Modules\TaskManager\Observers;

use Modules\TaskManager\Events\TaskCompleted;
use Modules\TaskManager\Models\Task;

class TaskObserver
{
    public function updated(Task $task): void
    {
        if ($task->wasChanged('status') && $task->status === 'completed') {
            $task->timestamps = false;
            $task->completed_at = now();
            $task->saveQuietly();
            $task->timestamps = true;

            TaskCompleted::dispatch($task);

            $this->createNextRecurrence($task);
        }

        if ($task->wasChanged('status') && $task->status !== 'completed') {
            $task->timestamps = false;
            $task->completed_at = null;
            $task->saveQuietly();
            $task->timestamps = true;
        }
    }

    private function createNextRecurrence(Task $task): void
    {
        if (! $task->is_recurring || ! $task->recurrence_type || ! $task->due_date) {
            return;
        }

        $nextDueDate = match ($task->recurrence_type) {
            'daily' => $task->due_date->copy()->addDay(),
            'weekly' => $task->due_date->copy()->addWeek(),
            'monthly' => $task->due_date->copy()->addMonth(),
        };

        if ($task->recurrence_ends_at && $nextDueDate->gt($task->recurrence_ends_at)) {
            return;
        }

        Task::create([
            'user_id' => $task->user_id,
            'category_id' => $task->category_id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => 'pending',
            'priority' => $task->priority,
            'is_pinned' => false,
            'is_recurring' => true,
            'recurrence_type' => $task->recurrence_type,
            'recurrence_ends_at' => $task->recurrence_ends_at,
            'due_date' => $nextDueDate,
        ]);
    }

    public function created(Task $task): void {}

    public function deleted(Task $task): void {}

    public function restored(Task $task): void {}

    public function forceDeleted(Task $task): void {}
}
