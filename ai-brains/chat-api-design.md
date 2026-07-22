# Chat — API Design (Phase 4)

> PLAN ONLY. All endpoints under `/api`, inside the existing `auth:sanctum` group. Responses follow existing conventions: single resource = bare (no `data` wrapper, via `JsonResource::withoutWrapping()`); collections wrapped under a named key or `data` + `meta` for paginated lists. Errors mirror the global handlers in `bootstrap/app.php` (401/403/404/422/500).

---

## Conventions

- **Auth:** every endpoint requires `auth:sanctum`. Missing/invalid token → `401 {"message":"Unauthenticated."}`.
- **Authorization:** participant/sender checks via `ConversationPolicy` / `MessagePolicy`. Denied → `403 {"message":"Forbidden."}` (friendly per-action message in controller).
- **Validation:** Form Requests; failure → `422 {"message":"Validation failed.","errors":{...}}`.
- **Not found / not a participant:** `404 {"message":"Conversation not found."}` (return 404 rather than 403 for non-participants to avoid leaking existence — see security doc).
- **Message pagination:** **cursor-based** (`?cursor=&limit=`). Conversation list: page-based (matches existing `meta` shape).
- **Rate limiting:** send endpoint uses `throttle:chat-send`.

---

## 1. List conversations

```
GET /api/conversations
```
- **Auth/Authz:** authenticated user; returns only conversations they participate in.
- **Query:** `?search=` (filter by other participant name), `?page=` (paginate, 15/page).
- **Sort:** `last_message_at DESC` (denormalized).
- **Response 200:**
```json
{
  "message": "Conversations fetched successfully",
  "data": [
    {
      "id": 12,
      "type": "direct",
      "other_participant": { "id": 5, "name": "Alice" },
      "last_message": { "id": 900, "body": "see you", "user_id": 5, "created_at": "..." },
      "unread_count": 3,
      "last_message_at": "..."
    }
  ],
  "meta": { "current_page": 1, "last_page": 4, "total": 37 }
}
```
- **Errors:** 401.

## 2. Create (or find) conversation

```
POST /api/conversations
```
- **Body:** `{ "user_id": 5 }` (the other participant).
- **Validation:** `user_id` required, exists in users, not equal to auth user.
- **Behavior:** find-or-create the direct conversation via `direct_hash`. Idempotent — returns existing if present (200) or new (201).
- **Authz:** any authenticated user may start a direct chat (future: respect blocking).
- **Response 201/200:** the `ConversationResource`.
- **Errors:** 422 (missing/invalid/self user_id), 401.

## 3. Open / show conversation

```
GET /api/conversations/{conversation}
```
- **Authz:** `view` policy — must be a participant.
- **Response 200:** conversation meta + participants (not messages — those are paginated separately).
- **Errors:** 404 (missing or not a participant), 401.

## 4. Fetch messages

```
GET /api/conversations/{conversation}/messages
```
- **Authz:** `view` policy (participant).
- **Query:** `?cursor=<opaque>&limit=30` (default 30, max 100). Cursor pagination on `id`.
- **Sort:** returns a page of messages ordered `id ASC` within the page; client requests older pages by passing the earliest cursor (infinite scroll upward).
- **Response 200:**
```json
{
  "message": "Messages fetched successfully",
  "data": [ { "id": 899, "body": "hi", "user_id": 5, "client_message_id": "uuid", "created_at": "..." } ],
  "meta": { "next_cursor": "eyJpZCI6ODcwfQ", "has_more": true }
}
```
- **Errors:** 404, 401.

## 5. Send message

```
POST /api/conversations/{conversation}/messages
```
- **Middleware:** `throttle:chat-send`.
- **Authz:** `sendMessage` policy (participant).
- **Body:** `{ "body": "hello", "client_message_id": "uuid-v4" }`.
- **Validation:** `body` required unless (future) attachment, string, max 5000, not blank after trim; `client_message_id` required, uuid, unique per conversation.
- **Behavior (in `DB::transaction`):** insert message → update `conversations.last_message_id` + `last_message_at` → dispatch `MessageSent` (broadcast after commit). Duplicate `client_message_id` returns the existing message (200, idempotent) instead of erroring.
- **Response 201:** the `MessageResource`. Duplicate → 200 same resource.
- **Errors:** 422 (empty/too long/missing key), 403→404 (not participant), 429 (rate limit), 401, 500.

## 6. Mark conversation as read

```
POST /api/conversations/{conversation}/read
```
- **Authz:** participant.
- **Body:** `{ "last_read_message_id": 900 }` (optional; defaults to newest).
- **Behavior:** set participant `last_read_at` / `last_read_message_id`. Idempotent.
- **Response 200:** `{ "message": "Marked as read", "unread_count": 0 }`.
- **Errors:** 404, 401.

## 7. Delete conversation

```
DELETE /api/conversations/{conversation}
```
- **Authz:** `delete` policy (participant).
- **Behavior (MVP):** soft-delete the thread for record-keeping (or "leave" semantics — decided at build; MVP = soft delete visible only to owner is future, MVP = soft delete thread).
- **Response 200:** `{ "message": "Conversation deleted successfully" }`.
- **Errors:** 404, 401.

## 8. Search users (to start a chat)

```
GET /api/users?search=ali
```
- **Authz:** authenticated.
- **Query:** `search` required (min 2 chars); paginate 10.
- **Response 200:** `{ "message": "...", "data": [ {"id":5,"name":"Alice"} ], "meta": {...} }` — minimal `ChatUserResource` (never expose email/password).
- **Errors:** 422 (search too short), 401.

## 9. Search conversations

Covered by endpoint #1 with `?search=`.

---

## FUTURE endpoints (reserved, not built in MVP)

| Endpoint | Purpose |
|----------|---------|
| `DELETE /api/messages/{message}` | Delete own message (`MessagePolicy@delete`, sender only) |
| `POST /api/conversations/{id}/typing` | Typing status → broadcast only, no persistence |
| `GET /api/users/{id}/presence` | Online status (presence channel) |
| `POST /api/conversations/{id}/participants` | Add participant (group chat) |
| `POST /api/messages/{id}/attachments` | File messages (mirrors existing attachments API) |

---

## Web (hybrid) routes — consume the same service, not duplicate logic

```
GET /chat                       → ChatPageController@index   (server-render list shell)
GET /chat/{conversation}        → ChatPageController@show    (server-render with thread preloaded)
```
These Blade routes call `ChatService` (the same one the API controllers use). Live actions (send, fetch older, mark read) happen through the JSON API above via axios. **Requires `routes/web.php` registration in `bootstrap/app.php` (Phase 0).**

## Route ordering note

Static/shallow routes (`/messages/{message}`, `/users`) and literal segments must be declared to avoid clashing with `{conversation}` wildcards — same discipline the existing `tasks/bulk` vs `tasks/{task}` ordering uses.
