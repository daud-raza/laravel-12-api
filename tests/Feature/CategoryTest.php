<?php

namespace Tests\Feature;

use Modules\TaskManager\Models\Category;
use Modules\TaskManager\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_category_endpoints_require_authentication(): void
    {
        $this->getJson('/api/categories')->assertStatus(401);
    }

    public function test_user_can_list_own_categories(): void
    {
        Category::factory()->count(2)->create(['user_id' => $this->user->id]);
        Category::factory()->create(['user_id' => User::factory()->create()->id]);

        $response = $this->actingAs($this->user)->getJson('/api/categories');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('categories'));
    }

    public function test_category_list_includes_task_count(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);
        Task::factory()->count(3)->create(['user_id' => $this->user->id, 'category_id' => $category->id]);

        $response = $this->actingAs($this->user)->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonPath('categories.0.tasks_count', 3);
    }

    public function test_user_can_create_category(): void
    {
        $this->actingAs($this->user)->postJson('/api/categories', [
            'name' => 'Work',
            'color' => '#FF5733',
        ])->assertStatus(201)
            ->assertJsonPath('category.name', 'Work');

        $this->assertDatabaseHas('categories', ['name' => 'Work', 'user_id' => $this->user->id]);
    }

    public function test_category_creation_requires_name(): void
    {
        $this->actingAs($this->user)->postJson('/api/categories', ['color' => '#FF5733'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_category_color_must_be_valid_hex(): void
    {
        $this->actingAs($this->user)->postJson('/api/categories', [
            'name' => 'Work',
            'color' => 'not-a-color',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    public function test_category_color_is_optional(): void
    {
        $this->actingAs($this->user)->postJson('/api/categories', ['name' => 'No Color'])
            ->assertStatus(201);
    }

    public function test_user_can_view_own_category(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson("/api/categories/{$category->id}")
            ->assertStatus(200)
            ->assertJsonPath('category.id', $category->id);
    }

    public function test_user_cannot_view_another_users_category(): void
    {
        $other = Category::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->getJson("/api/categories/{$other->id}")
            ->assertStatus(403);
    }

    public function test_user_can_update_own_category(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->putJson("/api/categories/{$category->id}", ['name' => 'Renamed'])
            ->assertStatus(200)
            ->assertJsonPath('category.name', 'Renamed');
    }

    public function test_user_cannot_update_another_users_category(): void
    {
        $other = Category::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->putJson("/api/categories/{$other->id}", ['name' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_user_can_delete_own_category(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->deleteJson("/api/categories/{$category->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_user_cannot_delete_another_users_category(): void
    {
        $other = Category::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->deleteJson("/api/categories/{$other->id}")
            ->assertStatus(403);
    }

    public function test_deleting_category_nullifies_task_category(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);
        $task = Task::factory()->create(['user_id' => $this->user->id, 'category_id' => $category->id]);

        $this->actingAs($this->user)->deleteJson("/api/categories/{$category->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'category_id' => null]);
    }
}
