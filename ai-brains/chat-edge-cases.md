# Chat — Edge Cases (Phase 9)

> PLAN ONLY. Senior-engineer failure-mode analysis with the chosen handling.

---

| # | Edge case | Handling |
|---|-----------|----------|
| 1 | **Simultaneous messages** (both users send at once) | Independent inserts; ordering by server `id` (monotonic). Both broadcast on the shared channel. No conflict. |
| 2 | **Duplicate send request** (retry, double-click, refresh mid-send) | `client_message_id` unique per conversation. Second insert hits the unique constraint → return the existing message (200), not a duplicate. Idempotent. |
| 3 | **Race creating a direct conversation** (both users tap "chat" together) | `direct_hash` unique. `find-or-create` inside a transaction; on unique-violation, catch and re-fetch the winner. Exactly one conversation results. |
| 4 | **Deleted user** (sender account removed) | `messages.user_id` → `nullOnDelete`. History survives; UI renders "Deleted user". Participant rows cascade out; conversation may become single-participant → shown read-only. |
| 5 | **Blocked users** (FUTURE) | Reserved: block list checked before create/send → 403. Not in MVP. |
| 6 | **Connection loss** (socket drop) | Client falls back to polling; on reconnect, fetch messages since the last known cursor. No gaps. |
| 7 | **Refresh during sending** | Optimistic bubble lost from DOM, but the `client_message_id` was (or will be) persisted; on reload the message appears from the server. If the request never reached the server, the composer retry / resend uses the same id → still safe. |
| 8 | **Invalid conversation id** | Route-model-binding miss → `404 {"message":"Conversation not found."}` (global handler). |
| 9 | **Unauthorized access** (non-participant) | Policy denies. Return **404** on show/messages (hide existence), **403** on explicit actions. |
| 10 | **Empty / whitespace-only message** | Validation: `body` required, trimmed, `min:1` after trim → 422. |
| 11 | **Large payload** | `body` `max:5000`; global request size limits apply. Oversized → 422 / 413. |
| 12 | **Timezone handling** | Store all timestamps in **UTC**; client formats to local. API returns ISO/UTC strings. |
| 13 | **Clock drift** | Ordering uses server `id`, never client time. `client_message_id` is for dedup only, not ordering. |
| 14 | **Database failure mid-send** | Whole send wrapped in `DB::transaction`; failure rolls back (no partial message, no `last_message_at` bump). Broadcast is post-commit so nothing is emitted. Controller `catch` → `Log::error` + 500, matching existing pattern. |
| 15 | **Broadcast after rollback** | Prevented: event dispatched only after commit (queued). |
| 16 | **Marking read out of order** | `last_read_message_id` only moves forward (`max(current, incoming)`); stale/older read calls are no-ops. |
| 17 | **Self-conversation** | `POST /conversations` rejects `user_id == auth id` (422). |
| 18 | **Conversation with no messages** | Allowed; list shows it with null last message / empty preview; sorts by `created_at` fallback. |
| 19 | **Message to a soft-deleted conversation** | `sendMessage` checks the conversation is active; deleted → 404. |
| 20 | **Very long thread scroll** | Cursor pagination + bounded DOM window (perf doc). |

## Concurrency invariants
- One direct conversation per user-pair (`direct_hash` unique).
- One message per (`conversation_id`, `client_message_id`) (unique).
- `last_read_*` monotonic non-decreasing.
- `last_message_at` reflects the newest committed message.
