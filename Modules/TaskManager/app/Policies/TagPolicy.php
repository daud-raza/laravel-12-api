<?php

namespace Modules\TaskManager\Policies;

use App\Models\User;
use Modules\TaskManager\Models\Tag;

class TagPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->id === $tag->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->id === $tag->user_id;
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->id === $tag->user_id;
    }

    public function restore(User $user, Tag $tag): bool
    {
        return $user->id === $tag->user_id;
    }

    public function forceDelete(User $user, Tag $tag): bool
    {
        return $user->id === $tag->user_id;
    }
}
