<?php

namespace App\Policies;

use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TimeLogPolicy
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

    public function update(User $user, TimeLog $timeLog): bool
    {
        return $timeLog->user_id === $user->id;
    }

    public function delete(User $user, TimeLog $timeLog): bool
    {
        return $timeLog->user_id === $user->id;
    }
}
