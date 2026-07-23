<?php

namespace Modules\TaskManager\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\TaskManager\Models\Task;

#[Signature('tasks:send-due-date-reminders')]
#[Description('Log tasks that are due tomorrow so reminders can be dispatched')]
class SendDueDateReminders extends Command
{
    public function handle(): int
    {
        $tomorrow = now()->addDay()->toDateString();

        $tasks = Task::with('user')
            ->where('due_date', $tomorrow)
            ->where('status', '!=', 'completed')
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No tasks due tomorrow.');

            return self::SUCCESS;
        }

        foreach ($tasks as $task) {
            // In a real app you would dispatch a notification/mail job here.
            // e.g. SendDueDateReminderJob::dispatch($task);
            $this->line("Reminder: [{$task->user->email}] Task \"{$task->title}\" is due tomorrow.");
        }

        $this->info("Processed {$tasks->count()} reminder(s).");

        return self::SUCCESS;
    }
}
