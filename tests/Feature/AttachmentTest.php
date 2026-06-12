<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->task = Task::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_user_can_list_attachments_on_own_task(): void
    {
        Attachment::factory()->count(2)->create(['task_id' => $this->task->id]);

        $response = $this->actingAs($this->user)->getJson("/api/tasks/{$this->task->id}/attachments");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('attachments'));
    }

    public function test_user_cannot_list_attachments_on_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->getJson("/api/tasks/{$other->id}/attachments")
            ->assertStatus(403);
    }

    public function test_user_can_upload_attachment(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('attachment.original_name', 'document.pdf');

        $this->assertDatabaseHas('attachments', [
            'task_id' => $this->task->id,
            'original_name' => 'document.pdf',
        ]);

        $stored = Attachment::first();
        Storage::disk('local')->assertExists($stored->path);
    }

    public function test_upload_requires_a_file(): void
    {
        $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/attachments", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_rejects_files_over_10mb(): void
    {
        $file = UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'); // 11 MB

        $this->actingAs($this->user)->postJson("/api/tasks/{$this->task->id}/attachments", [
            'file' => $file,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_user_cannot_upload_to_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)->postJson("/api/tasks/{$other->id}/attachments", [
            'file' => $file,
        ])->assertStatus(403);
    }

    public function test_user_can_delete_attachment_and_file_is_removed(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $path = $file->store("attachments/task-{$this->task->id}", 'local');
        $attachment = Attachment::factory()->create([
            'task_id' => $this->task->id,
            'path' => $path,
        ]);

        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->user)
            ->deleteJson("/api/tasks/{$this->task->id}/attachments/{$attachment->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_user_cannot_delete_attachment_on_another_users_task(): void
    {
        $other = Task::factory()->create(['user_id' => User::factory()->create()->id]);
        $attachment = Attachment::factory()->create(['task_id' => $other->id]);

        $this->actingAs($this->user)
            ->deleteJson("/api/tasks/{$other->id}/attachments/{$attachment->id}")
            ->assertStatus(403);
    }
}
