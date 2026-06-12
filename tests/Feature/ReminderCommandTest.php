<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_tasks_due_tomorrow(): void
    {
        $user = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'due_date' => now()->addDay()->format('Y-m-d'),
            'title' => 'Due Tomorrow',
        ]);

        $this->artisan('tasks:send-due-date-reminders')
            ->expectsOutputToContain('Due Tomorrow')
            ->assertExitCode(0);
    }

    public function test_command_ignores_completed_tasks(): void
    {
        $user = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'due_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $this->artisan('tasks:send-due-date-reminders')
            ->expectsOutput('No tasks due tomorrow.')
            ->assertExitCode(0);
    }

    public function test_command_ignores_tasks_not_due_tomorrow(): void
    {
        $user = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'due_date' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $this->artisan('tasks:send-due-date-reminders')
            ->expectsOutput('No tasks due tomorrow.')
            ->assertExitCode(0);
    }
}
