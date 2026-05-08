<?php

namespace App\Listeners;

use App\Events\TaskCompleted;
use Illuminate\Support\Facades\Log;

class LogTaskCompleted
{
    public function handle(TaskCompleted $event): void
    {
        Log::info('Task completed', [
            'task_id'      => $event->task->id,
            'title'        => $event->task->title,
            'user_id'      => $event->task->user_id,
            'completed_at' => $event->task->completed_at,
        ]);
    }
}
