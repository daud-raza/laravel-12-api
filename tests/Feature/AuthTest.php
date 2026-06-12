<?php

namespace Tests\Feature;

use App\Jobs\SendWelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Registration ────────────────────────────────────────────────

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'user', 'token'])
            ->assertJsonPath('user.email', 'test@example.com');

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_registration_returns_a_usable_token(): void
    {
        $token = $this->postJson('/api/auth/register', [
            'name' => 'Token User',
            'email' => 'token@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->json('token');

        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('user.email', 'token@example.com');
    }

    public function test_registration_dispatches_welcome_mail_job(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Mailed User',
            'email' => 'mailed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        Queue::assertPushed(SendWelcomeMail::class);
    }

    public function test_register_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Another User',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_requires_name_email_password(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_requires_password_confirmation_to_match(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Mismatch User',
            'email' => 'mismatch@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different456',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_requires_minimum_password_length(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Short Pass',
            'email' => 'short@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_rejects_invalid_email_format(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Bad Email',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ── Login ───────────────────────────────────────────────────────

    public function test_user_can_login(): void
    {
        User::factory()->create(['email' => 'login@example.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password',
        ])->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_login_fails_for_unknown_email(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'ghost@example.com',
            'password' => 'password',
        ])->assertStatus(401);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    // ── Profile / me ────────────────────────────────────────────────

    public function test_authenticated_user_can_fetch_own_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_profile_does_not_leak_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonMissingPath('user.password');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    // ── Logout ──────────────────────────────────────────────────────

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/auth/logout')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Logged out successfully');
    }

    public function test_logout_with_real_token_revokes_that_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }
}
