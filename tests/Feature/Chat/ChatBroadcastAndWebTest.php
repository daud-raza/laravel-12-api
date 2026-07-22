<?php

namespace Tests\Feature\Chat;

use Modules\Chat\Events\MessageSent;
use Modules\Chat\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatBroadcastAndWebTest extends TestCase
{
    use RefreshDatabase;

    private function directWith(User $a, User $b): Conversation
    {
        $c = Conversation::factory()->create([
            'direct_hash' => Conversation::directHash($a->id, $b->id),
        ]);
        $c->participants()->attach([$a->id, $b->id]);

        return $c;
    }

    public function test_sending_message_dispatches_broadcast_event(): void
    {
        Event::fake([MessageSent::class]);

        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = $this->directWith($a, $b);

        $this->actingAs($a)->postJson("/api/conversations/{$c->id}/messages", [
            'body' => 'ping',
            'client_message_id' => (string) Str::uuid(),
        ])->assertStatus(201);

        Event::assertDispatched(MessageSent::class, fn ($e) => $e->message->conversation_id === $c->id);
    }

    public function test_message_sent_broadcasts_on_private_conversation_channel(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = $this->directWith($a, $b);

        $message = $c->messages()->create([
            'user_id' => $a->id, 'body' => 'x', 'client_message_id' => (string) Str::uuid(),
        ]);
        $event = new MessageSent($message);
        $channels = $event->broadcastOn();

        $this->assertEquals('private-conversation.'.$c->id, $channels[0]->name);
    }

    // ── Web (session) ────────────────────────────────────────────────
    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertStatus(200)->assertSee('Log in');
    }

    public function test_guest_redirected_from_chat_to_login(): void
    {
        $this->get('/chat')->assertRedirect('/login');
    }

    public function test_user_can_login_via_session_and_reach_chat(): void
    {
        $user = User::factory()->create(['email' => 'web@example.com']); // factory password = 'password'

        $this->post('/login', ['email' => 'web@example.com', 'password' => 'password'])
            ->assertRedirect('/chat');

        $this->actingAs($user)->get('/chat')->assertStatus(200);
    }

    public function test_chat_page_embeds_api_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/chat')
            ->assertStatus(200)
            ->assertSee('name="api-token"', false);
    }

    public function test_non_participant_cannot_open_conversation_page(): void
    {
        $me = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = $this->directWith($a, $b);

        $this->actingAs($me)->get("/chat/{$c->id}")->assertStatus(404);
    }
}
