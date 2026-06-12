<?php

namespace Tests\Feature;

use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubtaskTest extends TestCase
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

    public function test_user_can_list_subtasks_with_meta_counts(): void
    {
        Subtask::factory()->create(['task_id' => $this->task->id, 'is_completed' => true]);
        Subtask::factory()->create(['task_id' => $this->task->id, 'is_completed' => false]);

        $response = $this->actingAs($this->user)->getJson("/api/tasks/{$this->task->id}/subtasks");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.completed', 1);
    }

    public function test_user_cannot_list_subtasks_on_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->getJson("/api/tasks/{$other->id}/subtasks")
            ->assertStatus(403);
    }

    public function test_user_can_create_subtask(): void
    {
        $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/subtasks", [
            'title' => 'Step one',
        ])->assertStatus(201)
            ->assertJsonPath('subtask.title', 'Step one');

        $this->assertDatabaseHas('subtasks', ['task_id' => $this->task->id, 'title' => 'Step one']);
    }

    public function test_subtask_title_is_required(): void
    {
        $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/subtasks", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_subtask_order_auto_increments(): void
    {
        $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/subtasks", ['title' => 'First']);
        $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/subtasks", ['title' => 'Second']);

        $orders = $this->task->subtasks()->orderBy('id')->pluck('order')->all();
        $this->assertEquals([0, 1], $orders);
    }

    public function test_user_cannot_add_subtask_to_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->postJson("/api/tasks/{$other->id}/subtasks", ['title' => 'X'])
            ->assertStatus(403);
    }

    public function test_user_can_update_subtask(): void
    {
        $subtask = Subtask::factory()->create(['task_id' => $this->task->id]);

        $this->actingAs($this->user)->putJson("/api/subtasks/{$subtask->id}", ['title' => 'Renamed'])
            ->assertStatus(200)
            ->assertJsonPath('subtask.title', 'Renamed');
    }

    public function test_user_cannot_update_subtask_on_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);
        $subtask = Subtask::factory()->create(['task_id' => $other->id]);

        $this->actingAs($this->user)->putJson("/api/subtasks/{$subtask->id}", ['title' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_user_can_toggle_subtask_completion(): void
    {
        $subtask = Subtask::factory()->create(['task_id' => $this->task->id, 'is_completed' => false]);

        $this->actingAs($this->user)->patchJson("/api/subtasks/{$subtask->id}/toggle")
            ->assertStatus(200);
        $this->assertTrue($subtask->fresh()->is_completed);

        $this->actingAs($this->user)->patchJson("/api/subtasks/{$subtask->id}/toggle")
            ->assertStatus(200);
        $this->assertFalse($subtask->fresh()->is_completed);
    }

    public function test_user_can_delete_subtask(): void
    {
        $subtask = Subtask::factory()->create(['task_id' => $this->task->id]);

        $this->actingAs($this->user)->deleteJson("/api/subtasks/{$subtask->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('subtasks', ['id' => $subtask->id]);
    }

    public function test_user_can_reorder_subtasks(): void
    {
        $a = Subtask::factory()->create(['task_id' => $this->task->id, 'order' => 0]);
        $b = Subtask::factory()->create(['task_id' => $this->task->id, 'order' => 1]);
        $c = Subtask::factory()->create(['task_id' => $this->task->id, 'order' => 2]);

        $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/subtasks/reorder", [
            'subtask_ids' => [$c->id, $a->id, $b->id],
        ])->assertStatus(200);

        $this->assertEquals(0, $c->fresh()->order);
        $this->assertEquals(1, $a->fresh()->order);
        $this->assertEquals(2, $b->fresh()->order);
    }

    public function test_reorder_requires_subtask_ids(): void
    {
        $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/subtasks/reorder", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subtask_ids']);
    }
}
