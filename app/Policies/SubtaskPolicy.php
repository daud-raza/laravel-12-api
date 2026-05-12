<?php

namespace App\Policies;

use App\Models\Subtask;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SubtaskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, \App\Models\Task $task): bool
    {
        return $task->user_id === $user->id;
    }

    public function create(User $user, \App\Models\Task $task): bool
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
