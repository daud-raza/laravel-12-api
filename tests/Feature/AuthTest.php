<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'user', 'token'])
                 ->assertJsonPath('user.email', 'test@example.com');

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_register_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'name'                  => 'Another User',
            'email'                 => 'taken@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422);
    }

    public function test_user_can_login(): void
    {
        User::factory()->create(['email' => 'login@example.com']);

        $this->postJson('/api/auth/login', [
            'email'    => 'login@example.com',
            'password' => 'password',
        ])->assertStatus(200)
          ->assertJsonStructure(['message', 'user', 'token']);
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_own_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/auth/me')
             ->assertStatus(200)
             ->assertJsonPath('user.id', $user->id);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/auth/logout')
             ->assertStatus(200)
             ->assertJsonPath('message', 'Logged out successfully');
    }
}
