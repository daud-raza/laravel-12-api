<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
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

    public function test_user_can_list_comments_on_own_task(): void
    {
        Comment::factory()->count(2)->create(['task_id' => $this->task->id, 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson("/api/tasks/{$this->task->id}/comments");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('comments'));
    }

    public function test_user_cannot_list_comments_on_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->getJson("/api/tasks/{$other->id}/comments")
            ->assertStatus(403);
    }

    public function test_user_can_add_comment(): void
    {
        $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/comments", [
            'body' => 'This is my comment',
        ])->assertStatus(201)
            ->assertJsonPath('comment.body', 'This is my comment');

        $this->assertDatabaseHas('comments', [
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
            'body' => 'This is my comment',
        ]);
    }

    public function test_comment_body_is_required(): void
    {
        $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/comments", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    public function test_comment_body_cannot_exceed_2000_chars(): void
    {
        $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/comments", [
            'body' => str_repeat('a', 2001),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    public function test_user_cannot_comment_on_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->postJson("/api/tasks/{$other->id}/comments", ['body' => 'hi'])
            ->assertStatus(403);
    }

    public function test_user_can_update_own_comment(): void
    {
        $comment = Comment::factory()->create(['task_id' => $this->task->id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->putJson("/api/comments/{$comment->id}", ['body' => 'Edited'])
            ->assertStatus(200)
            ->assertJsonPath('comment.body', 'Edited');
    }

    public function test_user_cannot_update_another_users_comment(): void
    {
        $stranger = User::factory()->create();
        $comment = Comment::factory()->create(['task_id' => $this->task->id, 'user_id' => $stranger->id]);

        $this->actingAs($this->user)->putJson("/api/comments/{$comment->id}", ['body' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_user_can_delete_own_comment(): void
    {
        $comment = Comment::factory()->create(['task_id' => $this->task->id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->deleteJson("/api/comments/{$comment->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_user_cannot_delete_another_users_comment(): void
    {
        $stranger = User::factory()->create();
        $comment = Comment::factory()->create(['task_id' => $this->task->id, 'user_id' => $stranger->id]);

        $this->actingAs($this->user)->deleteJson("/api/comments/{$comment->id}")
            ->assertStatus(403);
    }
}
