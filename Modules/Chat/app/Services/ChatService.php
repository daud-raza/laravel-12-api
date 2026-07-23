<?php

namespace Modules\Chat\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Chat\Events\MessageSent;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;

/**
 * Single source of truth for chat business logic. Used by BOTH the API
 * controllers and the web (Blade) controllers so there is no duplicated logic.
 */
class ChatService
{
    /**
     * Find the existing direct conversation between two users, or create it.
     * Race-safe via the unique direct_hash constraint.
     */
    public function findOrCreateDirect(User $me, int $otherUserId): Conversation
    {
        $hash = Conversation::directHash($me->id, $otherUserId);

        $existing = Conversation::where('direct_hash', $hash)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($me, $otherUserId, $hash) {
                $conversation = Conversation::create([
                    'type' => 'direct',
                    'created_by' => $me->id,
                    'direct_hash' => $hash,
                ]);

                $conversation->participants()->attach([
                    $me->id => ['joined_at' => now()],
                    $otherUserId => ['joined_at' => now()],
                ]);

                return $conversation;
            });
        } catch (QueryException $e) {
            // Concurrent create hit the unique(direct_hash) — return the winner.
            $winner = Conversation::where('direct_hash', $hash)->first();
            if ($winner) {
                return $winner;
            }
            throw $e;
        }
    }

    /**
     * Paginated list of the user's conversations, newest activity first,
     * with the other participant, last message, and unread count attached.
     */
    public function listConversations(User $me, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $me->conversations()
            ->with(['lastMessage', 'participants'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('conversations.id');

        if ($search) {
            $query->whereHas('participants', function ($q) use ($me, $search) {
                $q->where('users.id', '!=', $me->id)
                    ->where('users.name', 'like', '%'.$search.'%');
            });
        }

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function (Conversation $conversation) use ($me) {
            $this->decorate($conversation, $me);

            return $conversation;
        });

        return $paginator;
    }

    /**
     * Attach computed display attributes (other_participant, unread_count) to a
     * conversation so resources stay query-free.
     */
    public function decorate(Conversation $conversation, User $me): Conversation
    {
        $other = $conversation->relationLoaded('participants')
            ? $conversation->participants->firstWhere('id', '!=', $me->id)
            : $conversation->participants()->where('users.id', '!=', $me->id)->first();

        $conversation->setAttribute('other_participant', $other);
        $conversation->setAttribute('unread_count', $this->unreadCount($conversation, $me));

        return $conversation;
    }

    public function unreadCount(Conversation $conversation, User $me): int
    {
        $pivot = $conversation->participants()->whereKey($me->id)->first()?->pivot;
        $lastRead = $pivot?->last_read_message_id ?? 0;

        return $conversation->messages()
            ->where('id', '>', $lastRead)
            ->where('user_id', '!=', $me->id)
            ->count();
    }

    /**
     * Cursor-paginated messages for a conversation (oldest→newest within page).
     */
    public function messages(Conversation $conversation, int $limit = 30): CursorPaginator
    {
        return $conversation->messages()
            ->with('sender')
            ->orderByDesc('id')
            ->cursorPaginate(min($limit, 100));
    }

    /**
     * Send a message. Idempotent on (conversation, client_message_id).
     */
    public function sendMessage(Conversation $conversation, User $sender, string $body, string $clientMessageId): Message
    {
        $existing = $conversation->messages()
            ->where('client_message_id', $clientMessageId)
            ->first();

        if ($existing) {
            return $existing->load('sender');
        }

        $message = DB::transaction(function () use ($conversation, $sender, $body, $clientMessageId) {
            $message = $conversation->messages()->create([
                'user_id' => $sender->id,
                'body' => $body,
                'client_message_id' => $clientMessageId,
            ]);

            $conversation->forceFill([
                'last_message_id' => $message->id,
                'last_message_at' => $message->created_at,
            ])->save();

            return $message;
        });

        MessageSent::dispatch($message);

        return $message->load('sender');
    }

    /**
     * Mark a conversation read up to a given message (defaults to newest).
     * Monotonic — never moves the read marker backward.
     */
    public function markRead(Conversation $conversation, User $me, ?int $lastReadMessageId = null): int
    {
        $target = $lastReadMessageId ?? $conversation->messages()->max('id') ?? 0;

        $pivot = $conversation->participants()->whereKey($me->id)->first()?->pivot;
        $current = $pivot?->last_read_message_id ?? 0;
        $target = max($current, (int) $target);

        $conversation->participants()->updateExistingPivot($me->id, [
            'last_read_message_id' => $target,
            'last_read_at' => now(),
        ]);

        return $this->unreadCount($conversation->fresh(), $me);
    }

    public function searchUsers(User $me, string $term, int $perPage = 10): LengthAwarePaginator
    {
        return User::where('id', '!=', $me->id)
            ->where('name', 'like', '%'.$term.'%')
            ->orderBy('name')
            ->paginate($perPage);
    }
}
