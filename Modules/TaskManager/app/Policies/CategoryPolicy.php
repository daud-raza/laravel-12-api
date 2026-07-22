<?php

namespace Modules\TaskManager\Policies;

use App\Models\User;
use Modules\TaskManager\Models\Category;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }
}
