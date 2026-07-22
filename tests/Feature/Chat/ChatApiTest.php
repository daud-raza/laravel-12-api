<?php

namespace Tests\Feature\Chat;

use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->other = User::factory()->create();
    }

    private function directWith(User $a, User $b): Conversation
    {
        $c = Conversation::factory()->create([
            'direct_hash' => Conversation::directHash($a->id, $b->id),
        ]);
        $c->participants()->attach([$a->id, $b->id]);

        return $c;
    }

    // ── Auth ─────────────────────────────────────────────────────────
    public function test_chat_endpoints_require_auth(): void
    {
        $this->getJson('/api/conversations')->assertStatus(401);
    }

    // ── Create / find ────────────────────────────────────────────────
    public function test_can_create_direct_conversation(): void
    {
        $this->actingAs($this->user)->postJson('/api/conversations', ['user_id' => $this->other->id])
            ->assertStatus(201)
            ->assertJsonPath('type', 'direct');

        $this->assertDatabaseHas('conversations', [
            'direct_hash' => Conversation::directHash($this->user->id, $this->other->id),
        ]);
    }

    public function test_creating_existing_direct_returns_same_conversation(): void
    {
        $first = $this->actingAs($this->user)->postJson('/api/conversations', ['user_id' => $this->other->id])
            ->assertStatus(201)->json('id');

        $second = $this->actingAs($this->user)->postJson('/api/conversations', ['user_id' => $this->other->id])
            ->assertStatus(200)->json('id');

        $this->assertEquals($first, $second);
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_cannot_start_conversation_with_self(): void
    {
        $this->actingAs($this->user)->postJson('/api/conversations', ['user_id' => $this->user->id])
            ->assertStatus(422)->assertJsonValidationErrors(['user_id']);
    }

    public function test_create_requires_existing_user(): void
    {
        $this->actingAs($this->user)->postJson('/api/conversations', ['user_id' => 99999])
            ->assertStatus(422)->assertJsonValidationErrors(['user_id']);
    }

    // ── List ─────────────────────────────────────────────────────────
    public function test_lists_only_my_conversations(): void
    {
        $this->directWith($this->user, $this->other);
        $stranger = User::factory()->create();
        $this->directWith($stranger, User::factory()->create());

        $res = $this->actingAs($this->user)->getJson('/api/conversations')->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
    }

    // ── Show ─────────────────────────────────────────────────────────
    public function test_participant_can_view_conversation(): void
    {
        $c = $this->directWith($this->user, $this->other);
        $this->actingAs($this->user)->getJson("/api/conversations/{$c->id}")
            ->assertStatus(200)->assertJsonPath('id', $c->id);
    }

    public function test_non_participant_gets_404_on_show(): void
    {
        $stranger = User::factory()->create();
        $c = $this->directWith($this->other, $stranger);

        $this->actingAs($this->user)->getJson("/api/conversations/{$c->id}")
            ->assertStatus(404);
    }

    // ── Send ─────────────────────────────────────────────────────────
    public function test_participant_can_send_message(): void
    {
        $c = $this->directWith($this->user, $this->other);

        $this->actingAs($this->user)->postJson("/api/conversations/{$c->id}/messages", [
            'body' => 'hello there',
            'client_message_id' => (string) Str::uuid(),
        ])->assertStatus(201)->assertJsonPath('body', 'hello there');

        $this->assertDatabaseHas('messages', ['conversation_id' => $c->id, 'body' => 'hello there']);
        $this->assertEquals($c->fresh()->last_message_id, Message::first()->id);
    }

    public function test_send_is_idempotent_on_client_message_id(): void
    {
        $c = $this->directWith($this->user, $this->other);
        $cid = (string) Str::uuid();

        $a = $this->actingAs($this->user)->postJson("/api/conversations/{$c->id}/messages",
            ['body' => 'once', 'client_message_id' => $cid])->assertStatus(201)->json('id');
        $b = $this->actingAs($this->user)->postJson("/api/conversations/{$c->id}/messages",
            ['body' => 'once', 'client_message_id' => $cid])->assertStatus(200)->json('id');

        $this->assertEquals($a, $b);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_empty_message_rejected(): void
    {
        $c = $this->directWith($this->user, $this->other);
        $this->actingAs($this->user)->postJson("/api/conversations/{$c->id}/messages", [
            'body' => '   ', 'client_message_id' => (string) Str::uuid(),
        ])->assertStatus(422)->assertJsonValidationErrors(['body']);
    }

    public function test_non_participant_cannot_send(): void
    {
        $stranger = User::factory()->create();
        $c = $this->directWith($this->other, $stranger);

        $this->actingAs($this->user)->postJson("/api/conversations/{$c->id}/messages", [
            'body' => 'intrude', 'client_message_id' => (string) Str::uuid(),
        ])->assertStatus(403);
    }

    // ── Fetch messages ───────────────────────────────────────────────
    public function test_can_fetch_messages_with_cursor_meta(): void
    {
        $c = $this->directWith($this->user, $this->other);
        Message::factory()->count(5)->create(['conversation_id' => $c->id, 'user_id' => $this->other->id]);

        $this->actingAs($this->user)->getJson("/api/conversations/{$c->id}/messages?limit=2")
            ->assertStatus(200)
            ->assertJsonStructure(['message', 'data', 'meta' => ['next_cursor', 'has_more']])
            ->assertJsonPath('meta.has_more', true);
    }

    public function test_non_participant_cannot_fetch_messages(): void
    {
        $stranger = User::factory()->create();
        $c = $this->directWith($this->other, $stranger);
        $this->actingAs($this->user)->getJson("/api/conversations/{$c->id}/messages")
            ->assertStatus(404);
    }

    // ── Read ─────────────────────────────────────────────────────────
    public function test_mark_read_clears_unread(): void
    {
        $c = $this->directWith($this->user, $this->other);
        Message::factory()->count(3)->create(['conversation_id' => $c->id, 'user_id' => $this->other->id]);

        $this->actingAs($this->user)->postJson("/api/conversations/{$c->id}/read")
            ->assertStatus(200)->assertJsonPath('unread_count', 0);
    }

    public function test_unread_count_reflects_incoming(): void
    {
        $c = $this->directWith($this->user, $this->other);
        Message::factory()->count(2)->create(['conversation_id' => $c->id, 'user_id' => $this->other->id]);

        $res = $this->actingAs($this->user)->getJson('/api/conversations')->assertStatus(200);
        $this->assertEquals(2, $res->json('data.0.unread_count'));
    }

    // ── Delete ───────────────────────────────────────────────────────
    public function test_participant_can_delete_conversation(): void
    {
        $c = $this->directWith($this->user, $this->other);
        $this->actingAs($this->user)->deleteJson("/api/conversations/{$c->id}")
            ->assertStatus(200);
        $this->assertSoftDeleted('conversations', ['id' => $c->id]);
    }

    public function test_non_participant_cannot_delete(): void
    {
        $stranger = User::factory()->create();
        $c = $this->directWith($this->other, $stranger);
        $this->actingAs($this->user)->deleteJson("/api/conversations/{$c->id}")
            ->assertStatus(403);
    }

    // ── User search ──────────────────────────────────────────────────
    public function test_user_search_returns_matches_excluding_self(): void
    {
        User::factory()->create(['name' => 'Alice Cooper']);
        $res = $this->actingAs($this->user)->getJson('/api/users?search=Alice')->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertArrayNotHasKey('email', $res->json('data.0'));
    }

    public function test_user_search_requires_min_length(): void
    {
        $this->actingAs($this->user)->getJson('/api/users?search=a')
            ->assertStatus(422);
    }
}
