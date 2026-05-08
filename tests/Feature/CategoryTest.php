<?php

namespace Tests\Feature;

use App\Models\Category;
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

    public function test_user_can_list_own_categories(): void
    {
        Category::factory()->count(2)->create(['user_id' => $this->user->id]);
        Category::factory()->create(['user_id' => User::factory()->create()->id]);

        $response = $this->actingAs($this->user)->getJson('/api/categories');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('categories'));
    }

    public function test_user_can_create_category(): void
    {
        $this->actingAs($this->user)->postJson('/api/categories', [
            'name'  => 'Work',
            'color' => '#FF5733',
        ])->assertStatus(201)
          ->assertJsonPath('category.name', 'Work');
    }

    public function test_category_color_must_be_valid_hex(): void
    {
        $this->actingAs($this->user)->postJson('/api/categories', [
            'name'  => 'Work',
            'color' => 'not-a-color',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['color']);
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
}
