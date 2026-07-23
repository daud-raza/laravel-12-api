# Chat — Performance (Phase 8)

> PLAN ONLY.

---

## 1. Message pagination — cursor, not offset
Offset pagination (`LIMIT n OFFSET m`) degrades on long threads and skips/repeats rows when new messages arrive mid-scroll. **Cursor pagination on `id`** (`WHERE id < :cursor ORDER BY id DESC LIMIT n`) is O(index-seek), stable under concurrent inserts, and ideal for infinite scroll. Backed by composite index (`conversation_id`, `id`).

## 2. Conversation list — denormalized sort
List query orders by `conversations.last_message_at DESC` (indexed) and reads `last_message_id` for the preview — **no join or correlated subquery** per row. `last_message_at` is updated in the same transaction as message insert.

## 3. Unread count — cheap
Unread = messages in the conversation with `id > participant.last_read_message_id` (or `created_at > last_read_at`). Computed with a single indexed count per conversation, or batched via a subquery when listing. Avoids a per-message read table in MVP.

## 4. N+1 avoidance
- List: eager-load the other participant and last message; compute unread in one aggregated query. Never lazy-load inside a loop (same discipline as `TaskResource::whenLoaded`).
- Resources use `whenLoaded` so relations serialize only when explicitly loaded by the controller/service.

## 5. Indexes (from [chat-database-design.md](chat-database-design.md))
- `messages` (`conversation_id`, `id`) — thread fetch + cursor.
- `messages` unique(`conversation_id`, `client_message_id`) — idempotency lookup.
- `conversation_user` unique(`conversation_id`, `user_id`) + index(`user_id`) — membership + "my conversations".
- `conversations` unique(`direct_hash`), index(`last_message_at`).

## 6. Caching
- **User search:** short-TTL cache keyed by query term (results change rarely).
- **Participant set** per conversation cacheable for channel-auth + authorization hot path; invalidate on membership change (group-future).
- Do **not** cache message lists (freshness matters); rely on indexes.

## 7. Infinite scroll / lazy loading (web)
- Initial thread load = newest 30 via API. Scroll-up fetches older pages by cursor. DOM keeps a bounded window (optionally recycle very old nodes) to cap memory on huge threads.
- Server-rendered first paint (hybrid) shows the list immediately without waiting on JS.

## 8. Broadcast optimization
- Emit `MessageSent` only on the private conversation channel (2 recipients), never fan-out globally.
- Broadcast **after commit**, queued, so DB work isn't blocked and rolled-back writes never broadcast.
- Payload is the full `MessageResource` → client renders without a follow-up fetch (saves a round-trip).

## 9. Write path
- Single `DB::transaction`: insert message + update conversation denormalized fields. Two indexed writes. Event dispatch is queued (non-blocking).

## 10. Scale ceiling (honest)
MVP targets moderate volume on MySQL. At very high scale, later options (out of scope): partition `messages` by conversation/date, archive cold threads, move presence/typing to ephemeral store (Redis), read-replicas for list queries.
