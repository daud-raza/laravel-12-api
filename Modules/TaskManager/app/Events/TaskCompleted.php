<?php

namespace Modules\TaskManager\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\TaskManager\Models\Task;

class TaskCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Task $task) {}
}
