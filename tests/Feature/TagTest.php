<?php

namespace Tests\Feature;

use Modules\TaskManager\Models\Tag;
use Modules\TaskManager\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_list_own_tags(): void
    {
        Tag::factory()->count(2)->create(['user_id' => $this->user->id]);
        Tag::factory()->create(['user_id' => User::factory()->create()->id]);

        $response = $this->actingAs($this->user)->getJson('/api/tags');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('tags'));
    }

    public function test_user_can_create_tag(): void
    {
        $this->actingAs($this->user)->postJson('/api/tags', ['name' => 'Urgent'])
            ->assertStatus(201)
            ->assertJsonPath('tag.name', 'Urgent');

        $this->assertDatabaseHas('tags', ['name' => 'Urgent', 'user_id' => $this->user->id]);
    }

    public function test_tag_slug_is_auto_generated(): void
    {
        $this->actingAs($this->user)->postJson('/api/tags', ['name' => 'High Priority'])
            ->assertStatus(201)
            ->assertJsonPath('tag.slug', 'high-priority');
    }

    public function test_tag_name_is_required(): void
    {
        $this->actingAs($this->user)->postJson('/api/tags', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_tag_name_cannot_exceed_50_chars(): void
    {
        $this->actingAs($this->user)->postJson('/api/tags', ['name' => str_repeat('a', 51)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_delete_own_tag(): void
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->deleteJson("/api/tags/{$tag->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_user_cannot_delete_another_users_tag(): void
    {
        $other = Tag::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->deleteJson("/api/tags/{$other->id}")
            ->assertStatus(403);
    }

    // ── Sync tags onto a task ────────────────────────────────────────

    public function test_user_can_sync_tags_to_task(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);
        $tags = Tag::factory()->count(2)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->postJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => $tags->pluck('id')->all(),
        ])->assertStatus(200);

        $this->assertCount(2, $task->fresh()->tags);
    }

    public function test_syncing_replaces_existing_tags(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);
        $first = Tag::factory()->create(['user_id' => $this->user->id]);
        $second = Tag::factory()->create(['user_id' => $this->user->id]);

        $task->tags()->sync([$first->id]);

        $this->actingAs($this->user)->postJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [$second->id],
        ])->assertStatus(200);

        $tagIds = $task->fresh()->tags->pluck('id')->all();
        $this->assertEquals([$second->id], $tagIds);
    }

    public function test_cannot_sync_another_users_tags(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);
        $foreignTag = Tag::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->postJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [$foreignTag->id],
        ])->assertStatus(422);
    }

    public function test_cannot_sync_tags_on_another_users_task(): void
    {
        $foreignTask = Task::factory()->create(['user_id' => User::factory()->create()->id]);
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->postJson("/api/tasks/{$foreignTask->id}/tags", [
            'tag_ids' => [$tag->id],
        ])->assertStatus(403);
    }

    public function test_sync_requires_tag_ids_array(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->postJson("/api/tasks/{$task->id}/tags", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tag_ids']);
    }
}
