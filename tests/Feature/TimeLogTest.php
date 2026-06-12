<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeLogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->task = Task::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_user_can_list_time_logs_with_total(): void
    {
        TimeLog::factory()->create([
            'task_id' => $this->task->id, 'user_id' => $this->user->id, 'duration_minutes' => 30,
        ]);
        TimeLog::factory()->create([
            'task_id' => $this->task->id, 'user_id' => $this->user->id, 'duration_minutes' => 45,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/tasks/{$this->task->id}/time-logs");

        $response->assertStatus(200)
            ->assertJsonPath('total_minutes', 75);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_cannot_list_time_logs_on_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->getJson("/api/tasks/{$other->id}/time-logs")
            ->assertStatus(403);
    }

    public function test_starting_a_timer_creates_running_log(): void
    {
        $response = $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/time-logs", [
            'note' => 'Working',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Timer started successfully')
            ->assertJsonPath('time_log.ended_at', null);

        $this->assertDatabaseHas('time_logs', [
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
            'ended_at' => null,
        ]);
    }

    public function test_logging_a_finished_span_computes_duration(): void
    {
        $response = $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/time-logs", [
            'started_at' => now()->subHours(2)->toDateTimeString(),
            'ended_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Time log created successfully');

        $this->assertEqualsWithDelta(120, TimeLog::first()->duration_minutes, 1);
    }

    public function test_ended_at_must_be_after_started_at(): void
    {
        $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/time-logs", [
            'started_at' => now()->toDateTimeString(),
            'ended_at' => now()->subHour()->toDateTimeString(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['ended_at']);
    }

    public function test_user_cannot_log_time_on_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->postJson("/api/tasks/{$other->id}/time-logs", [])
            ->assertStatus(403);
    }

    public function test_user_can_stop_a_running_timer(): void
    {
        $log = TimeLog::factory()->running()->create([
            'task_id' => $this->task->id, 'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)->patchJson("/api/time-logs/{$log->id}/stop")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Timer stopped successfully');

        $fresh = $log->fresh();
        $this->assertNotNull($fresh->ended_at);
        $this->assertNotNull($fresh->duration_minutes);
    }

    public function test_stopping_an_already_stopped_timer_returns_422(): void
    {
        $log = TimeLog::factory()->create([
            'task_id' => $this->task->id, 'user_id' => $this->user->id, // factory default has ended_at
        ]);

        $this->actingAs($this->user)->patchJson("/api/time-logs/{$log->id}/stop")
            ->assertStatus(422);
    }

    public function test_user_cannot_stop_another_users_timer(): void
    {
        $stranger = User::factory()->create();
        $log = TimeLog::factory()->running()->create([
            'task_id' => $this->task->id, 'user_id' => $stranger->id,
        ]);

        $this->actingAs($this->user)->patchJson("/api/time-logs/{$log->id}/stop")
            ->assertStatus(403);
    }

    public function test_user_can_delete_own_time_log(): void
    {
        $log = TimeLog::factory()->create([
            'task_id' => $this->task->id, 'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)->deleteJson("/api/time-logs/{$log->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('time_logs', ['id' => $log->id]);
    }

    public function test_user_cannot_delete_another_users_time_log(): void
    {
        $stranger = User::factory()->create();
        $log = TimeLog::factory()->create([
            'task_id' => $this->task->id, 'user_id' => $stranger->id,
        ]);

        $this->actingAs($this->user)->deleteJson("/api/time-logs/{$log->id}")
            ->assertStatus(403);
    }
}
