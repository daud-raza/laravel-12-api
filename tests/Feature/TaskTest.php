<?php

namespace Tests\Feature;

use App\Models\Category;
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

    // ── Index / listing ─────────────────────────────────────────────

    public function test_user_can_list_own_tasks(): void
    {
        Task::factory()->count(3)->create(['user_id' => $this->user->id]);
        Task::factory()->create(['user_id' => User::factory()->create()->id]);

        $response = $this->actingAs($this->user)->getJson('/api/tasks');

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'data', 'meta' => ['current_page', 'last_page', 'total']]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_task_list_requires_authentication(): void
    {
        $this->getJson('/api/tasks')->assertStatus(401);
    }

    public function test_task_list_is_paginated_to_ten_per_page(): void
    {
        Task::factory()->count(15)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson('/api/tasks');

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total'));
        $this->assertEquals(2, $response->json('meta.last_page'));
    }

    public function test_pinned_tasks_appear_first(): void
    {
        Task::factory()->create(['user_id' => $this->user->id, 'is_pinned' => false, 'title' => 'Normal']);
        $pinned = Task::factory()->create(['user_id' => $this->user->id, 'is_pinned' => true, 'title' => 'Pinned']);

        $response = $this->actingAs($this->user)->getJson('/api/tasks');

        $this->assertEquals($pinned->id, $response->json('data.0.id'));
    }

    // ── Filtering ────────────────────────────────────────────────────

    public function test_task_filtering_by_status(): void
    {
        Task::factory()->create(['user_id' => $this->user->id, 'status' => 'pending']);
        Task::factory()->create(['user_id' => $this->user->id, 'status' => 'completed']);

        $response = $this->actingAs($this->user)->getJson('/api/tasks?status=pending');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_task_filtering_by_priority(): void
    {
        Task::factory()->create(['user_id' => $this->user->id, 'priority' => 'high']);
        Task::factory()->create(['user_id' => $this->user->id, 'priority' => 'low']);

        $response = $this->actingAs($this->user)->getJson('/api/tasks?priority=high');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_task_filtering_by_category(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);
        Task::factory()->create(['user_id' => $this->user->id, 'category_id' => $category->id]);
        Task::factory()->create(['user_id' => $this->user->id, 'category_id' => null]);

        $response = $this->actingAs($this->user)->getJson("/api/tasks?category_id={$category->id}");

        $this->assertCount(1, $response->json('data'));
    }

    public function test_task_search_by_title(): void
    {
        Task::factory()->create(['user_id' => $this->user->id, 'title' => 'Buy groceries']);
        Task::factory()->create(['user_id' => $this->user->id, 'title' => 'Write report']);

        $response = $this->actingAs($this->user)->getJson('/api/tasks?search=groceries');

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Buy groceries', $response->json('data.0.title'));
    }

    public function test_filters_can_be_combined(): void
    {
        Task::factory()->create(['user_id' => $this->user->id, 'status' => 'pending', 'priority' => 'high']);
        Task::factory()->create(['user_id' => $this->user->id, 'status' => 'pending', 'priority' => 'low']);

        $response = $this->actingAs($this->user)->getJson('/api/tasks?status=pending&priority=high');

        $this->assertCount(1, $response->json('data'));
    }

    // ── Create ───────────────────────────────────────────────────────

    public function test_user_can_create_task(): void
    {
        $this->actingAs($this->user)->postJson('/api/tasks', [
            'title' => 'My first task',
            'priority' => 'high',
        ])->assertStatus(201)
            ->assertJsonPath('title', 'My first task')
            ->assertJsonPath('priority', 'high');

        $this->assertDatabaseHas('tasks', ['title' => 'My first task', 'user_id' => $this->user->id]);
    }

    public function test_created_task_is_owned_by_authenticated_user(): void
    {
        $this->actingAs($this->user)->postJson('/api/tasks', ['title' => 'Mine'])
            ->assertStatus(201);

        $this->assertDatabaseHas('tasks', ['title' => 'Mine', 'user_id' => $this->user->id]);
    }

    public function test_task_creation_requires_title(): void
    {
        $this->actingAs($this->user)->postJson('/api/tasks', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_task_creation_rejects_invalid_status(): void
    {
        $this->actingAs($this->user)->postJson('/api/tasks', [
            'title' => 'Bad status',
            'status' => 'archived',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_task_creation_rejects_invalid_priority(): void
    {
        $this->actingAs($this->user)->postJson('/api/tasks', [
            'title' => 'Bad priority',
            'priority' => 'urgent',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_task_due_date_cannot_be_in_the_past(): void
    {
        $this->actingAs($this->user)->postJson('/api/tasks', [
            'title' => 'Past due',
            'due_date' => now()->subDay()->format('Y-m-d'),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    }

    public function test_task_creation_rejects_nonexistent_category(): void
    {
        $this->actingAs($this->user)->postJson('/api/tasks', [
            'title' => 'Bad category',
            'category_id' => 99999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_recurring_task_requires_recurrence_type(): void
    {
        $this->actingAs($this->user)->postJson('/api/tasks', [
            'title' => 'Recurring',
            'is_recurring' => true,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['recurrence_type']);
    }

    // ── Show ─────────────────────────────────────────────────────────

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

    public function test_viewing_missing_task_returns_404(): void
    {
        $this->actingAs($this->user)->getJson('/api/tasks/99999')
            ->assertStatus(404);
    }

    public function test_show_exposes_is_overdue_flag(): void
    {
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
            'due_date' => now()->subDays(2)->format('Y-m-d'),
        ]);

        $this->actingAs($this->user)->getJson("/api/tasks/{$task->id}")
            ->assertStatus(200)
            ->assertJsonPath('is_overdue', true);
    }

    // ── Update ───────────────────────────────────────────────────────

    public function test_user_can_update_own_task(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id, 'status' => 'pending']);

        $this->actingAs($this->user)->putJson("/api/tasks/{$task->id}", ['status' => 'completed'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'completed');
    }

    public function test_user_cannot_update_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->putJson("/api/tasks/{$other->id}", ['title' => 'Hacked'])
            ->assertStatus(403);

        $this->assertDatabaseMissing('tasks', ['id' => $other->id, 'title' => 'Hacked']);
    }

    public function test_completed_at_is_set_when_task_is_completed(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id, 'status' => 'pending']);

        $this->actingAs($this->user)->putJson("/api/tasks/{$task->id}", ['status' => 'completed']);

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_completed_at_is_cleared_when_task_reopened(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id, 'status' => 'pending']);

        $this->actingAs($this->user)->putJson("/api/tasks/{$task->id}", ['status' => 'completed']);
        $this->assertNotNull($task->fresh()->completed_at);

        $this->actingAs($this->user)->putJson("/api/tasks/{$task->id}", ['status' => 'in_progress']);
        $this->assertNull($task->fresh()->completed_at);
    }

    // ── Delete / restore ─────────────────────────────────────────────

    public function test_user_can_soft_delete_task(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->deleteJson("/api/tasks/{$task->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_user_cannot_delete_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->deleteJson("/api/tasks/{$other->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('tasks', ['id' => $other->id, 'deleted_at' => null]);
    }

    public function test_user_can_restore_soft_deleted_task(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);
        $task->delete();

        $this->actingAs($this->user)->postJson("/api/tasks/{$task->id}/restore")
            ->assertStatus(200);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deleted_at' => null]);
    }

    public function test_restoring_unknown_task_returns_404(): void
    {
        $this->actingAs($this->user)->postJson('/api/tasks/99999/restore')
            ->assertStatus(404);
    }

    public function test_user_cannot_restore_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);
        $other->delete();

        $this->actingAs($this->user)->postJson("/api/tasks/{$other->id}/restore")
            ->assertStatus(403);
    }

    // ── Pin ──────────────────────────────────────────────────────────

    public function test_user_can_pin_and_unpin_task(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id, 'is_pinned' => false]);

        $this->actingAs($this->user)->postJson("/api/tasks/{$task->id}/pin")
            ->assertStatus(200);
        $this->assertTrue($task->fresh()->is_pinned);

        $this->actingAs($this->user)->postJson("/api/tasks/{$task->id}/pin")
            ->assertStatus(200);
        $this->assertFalse($task->fresh()->is_pinned);
    }

    public function test_user_cannot_pin_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->postJson("/api/tasks/{$other->id}/pin")
            ->assertStatus(403);
    }

    // ── Bulk ─────────────────────────────────────────────────────────

    public function test_bulk_update_status(): void
    {
        $tasks = Task::factory()->count(3)->create(['user_id' => $this->user->id, 'status' => 'pending']);

        $this->actingAs($this->user)->postJson('/api/tasks/bulk', [
            'task_ids' => $tasks->pluck('id')->all(),
            'action' => 'update_status',
            'value' => 'completed',
        ])->assertStatus(200);

        foreach ($tasks as $task) {
            $this->assertEquals('completed', $task->fresh()->status);
        }
    }

    public function test_bulk_delete(): void
    {
        $tasks = Task::factory()->count(2)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->postJson('/api/tasks/bulk', [
            'task_ids' => $tasks->pluck('id')->all(),
            'action' => 'delete',
        ])->assertStatus(200);

        foreach ($tasks as $task) {
            $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        }
    }

    public function test_bulk_only_affects_own_tasks(): void
    {
        $mine = Task::factory()->create(['user_id' => $this->user->id, 'status' => 'pending']);
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id, 'status' => 'pending']);

        $this->actingAs($this->user)->postJson('/api/tasks/bulk', [
            'task_ids' => [$mine->id, $other->id],
            'action' => 'update_status',
            'value' => 'completed',
        ])->assertStatus(200);

        $this->assertEquals('completed', $mine->fresh()->status);
        $this->assertEquals('pending', $other->fresh()->status);
    }

    public function test_bulk_requires_value_unless_deleting(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->postJson('/api/tasks/bulk', [
            'task_ids' => [$task->id],
            'action' => 'update_status',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    }

    public function test_bulk_rejects_invalid_action(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->postJson('/api/tasks/bulk', [
            'task_ids' => [$task->id],
            'action' => 'explode',
            'value' => 'x',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['action']);
    }

    public function test_bulk_with_no_owned_tasks_returns_404(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->postJson('/api/tasks/bulk', [
            'task_ids' => [$other->id],
            'action' => 'update_status',
            'value' => 'completed',
        ])->assertStatus(404);
    }

    // ── Recurrence ────────────────────────────────────────────────────

    public function test_completing_recurring_task_spawns_next_occurrence(): void
    {
        $task = Task::factory()->recurring('daily')->create(['user_id' => $this->user->id]);

        $this->assertDatabaseCount('tasks', 1);

        $this->actingAs($this->user)->putJson("/api/tasks/{$task->id}", ['status' => 'completed'])
            ->assertStatus(200);

        // Original completed + one freshly spawned pending occurrence.
        $this->assertDatabaseCount('tasks', 2);
        $this->assertEquals(1, Task::where('status', 'pending')->where('is_recurring', true)->count());
    }

    public function test_non_recurring_task_does_not_spawn_occurrence(): void
    {
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
            'is_recurring' => false,
        ]);

        $this->actingAs($this->user)->putJson("/api/tasks/{$task->id}", ['status' => 'completed']);

        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_recurrence_stops_after_end_date(): void
    {
        $task = Task::factory()->recurring('daily')->create([
            'user_id' => $this->user->id,
            'due_date' => now()->format('Y-m-d'),
            'recurrence_ends_at' => now()->format('Y-m-d'),
        ]);

        $this->actingAs($this->user)->putJson("/api/tasks/{$task->id}", ['status' => 'completed']);

        // Next due date would exceed the end date → no new task created.
        $this->assertDatabaseCount('tasks', 1);
    }
}
