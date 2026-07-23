<?php

namespace Tests\Unit;

use Modules\TaskManager\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-level coverage of the TaskObserver recurrence + completed_at logic,
 * driven directly through the model (no HTTP layer).
 */
class TaskRecurrenceTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecurring(string $type, string $dueDate, ?string $endsAt = null): Task
    {
        return Task::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'pending',
            'is_recurring' => true,
            'recurrence_type' => $type,
            'due_date' => $dueDate,
            'recurrence_ends_at' => $endsAt,
        ]);
    }

    public function test_daily_recurrence_advances_due_date_by_one_day(): void
    {
        $task = $this->makeRecurring('daily', now()->format('Y-m-d'));
        $task->update(['status' => 'completed']);

        $next = Task::where('id', '!=', $task->id)->first();
        $this->assertNotNull($next);
        $this->assertEquals(
            now()->addDay()->format('Y-m-d'),
            $next->due_date->format('Y-m-d')
        );
        $this->assertEquals('pending', $next->status);
    }

    public function test_weekly_recurrence_advances_due_date_by_one_week(): void
    {
        $task = $this->makeRecurring('weekly', now()->format('Y-m-d'));
        $task->update(['status' => 'completed']);

        $next = Task::where('id', '!=', $task->id)->first();
        $this->assertEquals(
            now()->addWeek()->format('Y-m-d'),
            $next->due_date->format('Y-m-d')
        );
    }

    public function test_monthly_recurrence_advances_due_date_by_one_month(): void
    {
        $task = $this->makeRecurring('monthly', now()->format('Y-m-d'));
        $task->update(['status' => 'completed']);

        $next = Task::where('id', '!=', $task->id)->first();
        $this->assertEquals(
            now()->addMonth()->format('Y-m-d'),
            $next->due_date->format('Y-m-d')
        );
    }

    public function test_recurrence_does_not_spawn_past_the_end_date(): void
    {
        $task = $this->makeRecurring('daily', now()->format('Y-m-d'), now()->format('Y-m-d'));
        $task->update(['status' => 'completed']);

        $this->assertEquals(1, Task::count());
    }

    public function test_completing_sets_completed_at(): void
    {
        $task = Task::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'pending',
        ]);

        $task->update(['status' => 'completed']);

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_reopening_clears_completed_at(): void
    {
        $task = Task::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'pending',
        ]);

        $task->update(['status' => 'completed']);
        $task->update(['status' => 'in_progress']);

        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_completing_without_status_change_is_noop(): void
    {
        $task = Task::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'completed',
        ]);
        $task->refresh();

        // Update an unrelated field; status does not change → no new recurrence.
        $task->update(['title' => 'Renamed only']);

        $this->assertEquals(1, Task::count());
    }
}
