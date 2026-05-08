<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AttachmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Attachment $attachment): bool
    {
        return $user->id === $attachment->task->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Attachment $attachment): bool
    {
        return $user->id === $attachment->task->user_id;
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $user->id === $attachment->task->user_id;
    }

    public function restore(User $user, Attachment $attachment): bool
    {
        return $user->id === $attachment->task->user_id;
    }

    public function forceDelete(User $user, Attachment $attachment): bool
    {
        return $user->id === $attachment->task->user_id;
    }
}
