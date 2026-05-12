<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Subtask;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TimeLog;
use App\Policies\AttachmentPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CommentPolicy;
use App\Policies\SubtaskPolicy;
use App\Policies\TagPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TimeLogPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Task::class       => TaskPolicy::class,
        Category::class   => CategoryPolicy::class,
        Comment::class    => CommentPolicy::class,
        Tag::class        => TagPolicy::class,
        Attachment::class => AttachmentPolicy::class,
        Subtask::class    => SubtaskPolicy::class,
        TimeLog::class    => TimeLogPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
