<?php

namespace Modules\TaskManager\Policies;

use App\Models\User;
use Modules\TaskManager\Models\Task;
use Modules\TaskManager\Models\TimeLog;

class TimeLogPolicy
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

    public function update(User $user, TimeLog $timeLog): bool
    {
        return $timeLog->user_id === $user->id;
    }

    public function delete(User $user, TimeLog $timeLog): bool
    {
        return $timeLog->user_id === $user->id;
    }
}
