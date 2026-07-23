<?php

namespace Modules\TaskManager\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\TaskManager\Console\SendDueDateReminders;
use Modules\TaskManager\Events\TaskCompleted;
use Modules\TaskManager\Listeners\LogTaskCompleted;
use Modules\TaskManager\Models\Attachment;
use Modules\TaskManager\Models\Category;
use Modules\TaskManager\Models\Comment;
use Modules\TaskManager\Models\Subtask;
use Modules\TaskManager\Models\Tag;
use Modules\TaskManager\Models\Task;
use Modules\TaskManager\Models\TimeLog;
use Modules\TaskManager\Observers\TaskObserver;
use Modules\TaskManager\Policies\AttachmentPolicy;
use Modules\TaskManager\Policies\CategoryPolicy;
use Modules\TaskManager\Policies\CommentPolicy;
use Modules\TaskManager\Policies\SubtaskPolicy;
use Modules\TaskManager\Policies\TagPolicy;
use Modules\TaskManager\Policies\TaskPolicy;
use Modules\TaskManager\Policies\TimeLogPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class TaskManagerServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'TaskManager';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'taskmanager';

    public function boot(): void
    {
        parent::boot();

        // Observer
        Task::observe(TaskObserver::class);

        // Policies
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(Subtask::class, SubtaskPolicy::class);
        Gate::policy(TimeLog::class, TimeLogPolicy::class);

        // Events
        Event::listen(TaskCompleted::class, LogTaskCompleted::class);
    }

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SendDueDateReminders::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('tasks:send-due-date-reminders')->dailyAt('08:00');
    }
}
