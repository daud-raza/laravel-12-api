<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_list_own_tasks(): void
    {
        Task::factory()->count(3)->create(['user_id' => $this->user->id]);
        Task::factory()->create(['user_id' => User::factory()->create()->id]); // another user

        $response = $this->actingAs($this->user)->getJson('/api/tasks');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_user_can_create_task(): void
    {
        $this->actingAs($this->user)->postJson('/api/tasks', [
            'title'    => 'My first task',
            'priority' => 'high',
        ])->assertStatus(201)
          ->assertJsonPath('title', 'My first task');

        $this->assertDatabaseHas('tasks', ['title' => 'My first task', 'user_id' => $this->user->id]);
    }

    public function test_task_creation_requires_title(): void
    {
        $this->actingAs($this->user)->postJson('/api/tasks', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['title']);
    }

    public function test_user_can_view_own_task(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson("/api/tasks/{$task->id}")
             ->assertStatus(200)
             ->assertJsonPath('id', $task->id);
    }

    public function test_user_cannot_view_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->getJson("/api/tasks/{$other->id}")
             ->assertStatus(403);
    }

    public function test_user_can_update_own_task(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id, 'status' => 'pending']);

        $this->actingAs($this->user)->putJson("/api/tasks/{$task->id}", ['status' => 'completed'])
             ->assertStatus(200)
             ->assertJsonPath('status', 'completed');
    }

    public function test_completed_at_is_set_when_task_is_completed(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id, 'status' => 'pending']);

        $this->actingAs($this->user)->putJson("/api/tasks/{$task->id}", ['status' => 'completed']);

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_user_can_soft_delete_task(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->deleteJson("/api/tasks/{$task->id}")
             ->assertStatus(200);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_task_filtering_by_status(): void
    {
        Task::factory()->create(['user_id' => $this->user->id, 'status' => 'pending']);
        Task::factory()->create(['user_id' => $this->user->id, 'status' => 'completed']);

        $response = $this->actingAs($this->user)->getJson('/api/tasks?status=pending');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }
}
