<?php

namespace Modules\TaskManager\Policies;

use App\Models\User;
use Modules\TaskManager\Models\Attachment;

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
