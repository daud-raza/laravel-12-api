<?php

namespace Modules\TaskManager\Policies;

use App\Models\User;
use Modules\TaskManager\Models\Subtask;
use Modules\TaskManager\Models\Task;

class SubtaskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Task $task): bool
    {
        return $task->user_id === $user->id;
    }

    public function create(User $user, Task $task): bool
    {
        return $task->user_id === $user->id;
    }

    public function update(User $user, Subtask $subtask): bool
    {
        return $subtask->task->user_id === $user->id;
    }

    public function delete(User $user, Subtask $subtask): bool
    {
        return $subtask->task->user_id === $user->id;
    }
}
