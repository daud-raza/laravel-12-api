<?php

namespace Tests\Feature\Chat;

use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_has_participants(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = Conversation::factory()->create();
        $c->participants()->attach([$a->id, $b->id]);

        $this->assertCount(2, $c->fresh()->participants);
    }

    public function test_user_can_access_their_conversations(): void
    {
        $user = User::factory()->create();
        $c = Conversation::factory()->create();
        $c->participants()->attach($user->id);

        $this->assertCount(1, $user->fresh()->conversations);
    }

    public function test_conversation_has_many_messages(): void
    {
        $c = Conversation::factory()->create();
        Message::factory()->count(3)->create(['conversation_id' => $c->id]);

        $this->assertCount(3, $c->fresh()->messages);
    }

    public function test_message_belongs_to_conversation_and_sender(): void
    {
        $user = User::factory()->create();
        $c = Conversation::factory()->create();
        $m = Message::factory()->create(['conversation_id' => $c->id, 'user_id' => $user->id]);

        $this->assertEquals($c->id, $m->conversation->id);
        $this->assertEquals($user->id, $m->sender->id);
    }

    public function test_last_message_relationship(): void
    {
        $c = Conversation::factory()->create();
        $m = Message::factory()->create(['conversation_id' => $c->id]);
        $c->update(['last_message_id' => $m->id, 'last_message_at' => now()]);

        $this->assertEquals($m->id, $c->fresh()->lastMessage->id);
    }

    public function test_direct_hash_is_order_independent(): void
    {
        $this->assertEquals(
            Conversation::directHash(5, 9),
            Conversation::directHash(9, 5)
        );
        $this->assertNotEquals(
            Conversation::directHash(1, 2),
            Conversation::directHash(1, 3)
        );
    }

    public function test_participant_pivot_stores_read_state(): void
    {
        $user = User::factory()->create();
        $c = Conversation::factory()->create();
        $c->participants()->attach($user->id, ['last_read_message_id' => 42, 'last_read_at' => now()]);

        $this->assertEquals(42, $c->participants->first()->pivot->last_read_message_id);
    }

    public function test_deleting_conversation_cascades_messages(): void
    {
        $c = Conversation::factory()->create();
        Message::factory()->count(2)->create(['conversation_id' => $c->id]);
        $c->forceDelete();

        $this->assertEquals(0, Message::where('conversation_id', $c->id)->count());
    }

    public function test_message_soft_deletes(): void
    {
        $m = Message::factory()->create();
        $m->delete();

        $this->assertSoftDeleted('messages', ['id' => $m->id]);
    }

    public function test_client_message_id_unique_per_conversation(): void
    {
        $c = Conversation::factory()->create();
        Message::factory()->create(['conversation_id' => $c->id, 'client_message_id' => 'dup']);

        $this->expectException(QueryException::class);
        Message::factory()->create(['conversation_id' => $c->id, 'client_message_id' => 'dup']);
    }
}
