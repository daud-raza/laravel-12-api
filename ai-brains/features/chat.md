# Feature — One-to-One Chat Module

> **Status:** PLAN ONLY — no code written yet. Awaiting approval before implementation.
> Part of the Laravel 13 Task Manager API. See sibling docs: [chat-architecture.md](../chat-architecture.md), [chat-api-design.md](../chat-api-design.md), [chat-database-design.md](../chat-database-design.md), [chat-security.md](../chat-security.md), [chat-performance.md](../chat-performance.md), [chat-edge-cases.md](../chat-edge-cases.md), [chat-development-roadmap.md](../chat-development-roadmap.md).

---

## 1. Summary

A self-contained Chat module providing **one-to-one (direct) messaging** between authenticated users. Built API-first on the existing architecture; the web UI (Blade) consumes the same backend logic. Designed so **group chat** can be added later without a schema rewrite.

## 2. Goals

- Direct 1:1 conversations between two users.
- Send / receive text messages.
- Conversation list with last message + unread count.
- Mark conversation as read.
- Search users (to start a chat) and search conversations.
- Real-time delivery (recommended: Laravel Reverb) with a polling fallback for MVP.
- Blade web frontend that reuses the same business logic as the API — no duplication.

## 3. Non-Goals (this phase)

- Group chat (schema is future-proofed for it, but not built).
- Attachments/file messages (schema reserves space; not built).
- Message editing/deletion (API reserved; not built in MVP).
- Blocking, typing indicators, presence/online status (reserved as future events).

## 4. Why this design fits the existing project

The current app is a **slim Laravel 13 JSON API** with: Sanctum token auth, Policy-based authorization, API Resources, Form Requests, `DB::transaction` writes, an Observer + Event/Listener example, a queued Job, and rate limiters. See [../features/PROJECT_BRAIN.md](../features/PROJECT_BRAIN.md).

The Chat module mirrors every one of those conventions (detailed in [chat-architecture.md](../chat-architecture.md)). The single **new** structural element is a thin **ChatService** — justified because the chosen web strategy (Blade server-render + API hybrid) means both the API controllers and the web controllers must run the same logic. A shared service is the only clean way to satisfy the "no duplicate business logic" requirement.

## 5. Key design decisions (rationale in the linked docs)

| Decision | Choice | Why |
|----------|--------|-----|
| Conversation model | `conversations` + `conversation_user` pivot (participants) | Many-to-many from day one → group chat = just add participants |
| Direct-chat uniqueness | `direct_hash` column (sorted user-id pair) unique | Guarantees one direct conversation per pair; avoids race duplicates |
| Message ordering | Server `id` (bigint, monotonic) + `client_message_id` for dedup | Robust under clock drift and double-send |
| Message pagination | Cursor-based (not offset) | Stable under concurrent inserts; performant on large threads |
| Unread tracking | `last_read_at` on participant pivot | Cheap unread count; no per-message read table needed for MVP |
| Web frontend | Blade server-render + API hybrid over a shared ChatService | Reuses logic; no Vue; progressive enhancement |
| Real-time | Laravel Reverb (primary) + polling fallback | First-party, free, self-hosted; polling keeps MVP shippable |

## 6. Integration points with existing system

- **Auth:** reuses `auth:sanctum`. No new auth.
- **User model:** adds `conversations()` / `messages()` relationships. No column changes required for MVP.
- **Authorization:** new `ConversationPolicy`, `MessagePolicy`, registered via `Gate::policy()` in `AppServiceProvider` (same as existing policies).
- **Events/Queue:** new `MessageSent` event (implements `ShouldBroadcast`), reusing the existing `Event::listen` registration style and queue infrastructure.
- **Routing:** new chat routes appended to `routes/api.php`; **web routes require `routes/web.php` to be registered in `bootstrap/app.php` first** (currently it is not — see roadmap Phase 0).
- **Rate limiting:** a new `chat-send` limiter added alongside `api` / `auth`.

## 7. Risks & assumptions

- **Assumption:** MySQL remains the datastore; messages volume is moderate (thousands, not billions) for MVP. High scale would later warrant partitioning/archival (out of scope).
- **Risk:** `routes/web.php` is not wired into the slim bootstrap. Blade UI cannot work until that is fixed (small, contained change; Phase 0).
- **Risk:** Real-time adds infra (Reverb server / queue worker). Mitigated by shipping MVP on polling first, layering Reverb in Phase 5.
- **Assumption:** No existing presence/notification system to reuse — Chat introduces its own event, kept minimal.

## 8. Future enhancements

Group chat · attachments · message edit/delete · typing indicators · presence/online · read receipts per message · push/email notifications on missed messages · message reactions · blocking. All reserved in schema/API design so they slot in without breaking changes.
