<?php

namespace App\Providers;

use App\Events\TaskCompleted;
use App\Listeners\LogTaskCompleted;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Subtask;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TimeLog;
use App\Observers\TaskObserver;
use App\Policies\AttachmentPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CommentPolicy;
use App\Policies\SubtaskPolicy;
use App\Policies\TagPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TimeLogPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Observer
        Task::observe(TaskObserver::class);

        // API Resources
        JsonResource::withoutWrapping();

        // Policies
        Gate::policy(Task::class,       TaskPolicy::class);
        Gate::policy(Category::class,   CategoryPolicy::class);
        Gate::policy(Comment::class,    CommentPolicy::class);
        Gate::policy(Tag::class,        TagPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(Subtask::class,    SubtaskPolicy::class);
        Gate::policy(TimeLog::class,    TimeLogPolicy::class);

        // Events
        Event::listen(TaskCompleted::class, LogTaskCompleted::class);

        // Rate limiters
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again later.',
                    ], 429);
                });
        });
    }
}
