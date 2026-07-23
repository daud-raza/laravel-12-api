# Chat — Security (Phase 7)

> PLAN ONLY.

---

## 1. Authentication
Reuses `auth:sanctum`. Every API and web chat route sits behind it. Broadcast channels authenticated via Sanctum in `routes/channels.php`. No new auth surface.

## 2. Authorization & conversation ownership
- `ConversationPolicy`: `view`, `sendMessage`, `delete` all require the user to be a **participant** (`conversation.users` contains `user->id`). Mirrors the existing ownership pattern (`$user->id === $model->user_id`), extended to a membership check.
- `MessagePolicy@delete` (future): sender only.
- Controllers call `$this->authorize(...)`; denial → 403 handled globally.
- **Existence hiding:** for non-participants, return **404 not 403** on show/messages so the API doesn't confirm a conversation exists to outsiders.

## 3. User validation
- `POST /conversations` validates `user_id` `exists:users,id` and `!= auth id` (no self-chat).
- User search returns a **minimal resource** (`id`, `name` only) — never email, tokens, or internal fields.
- Deleted target user → `exists` rule fails → 422.

## 4. Rate limiting & spam protection
- New `chat-send` limiter (e.g. 30/min per user) in `AppServiceProvider`, alongside existing `api`(60) and `auth`(10). Prevents flooding.
- Conversation creation limited by the global `api` limiter.
- `client_message_id` uniqueness prevents accidental duplicate storms.
- Future: content heuristics / block list.

## 5. XSS prevention
- **Web render:** Blade `{{ $message->body }}` auto-escapes HTML. Never use `{!! !!}` for user content.
- **API:** returns raw text; the JS client inserts via `textContent` (not `innerHTML`) when rendering live messages.
- Body stored as plain text; no HTML/markdown rendering in MVP.

## 6. CSRF
- **API:** stateless token auth (Sanctum bearer) — CSRF not applicable.
- **Web pages:** served under the `web` middleware group → CSRF token required on any state-changing form. Since state changes go through the token-authenticated API (axios with bearer), CSRF is naturally sidestepped; if cookie-based session is used for web, include the `X-CSRF-TOKEN` header.

## 7. API security (general)
- HTTPS assumed in production (Sanctum tokens in transit).
- Input validated + length-capped (`body` max 5000) to bound payloads.
- Mass-assignment: models use explicit `$fillable`; sender `user_id` set from `auth()`, never from request body.
- Broadcast payload contains only the `MessageResource` (no sensitive fields).

## 8. Broadcast channel security
- `PrivateChannel("conversation.{id}")` authorized in `routes/channels.php`: callback returns `true` only if the authenticated user is a participant. Prevents eavesdropping on others' threads.

## 9. File upload security — FUTURE
When attachments land: validate mime + size (reuse existing 10 MB attachment rule), store on non-public disk, stream via authorized route, never trust client filename, consider AV scan. Reserved, not in MVP.

## 10. Checklist
- [ ] Participant-only policies on every conversation/message action
- [ ] 404 (not 403) for non-participants to hide existence
- [ ] `chat-send` throttle registered
- [ ] User search resource excludes email/sensitive fields
- [ ] Blade auto-escaping; JS uses `textContent`
- [ ] Channel auth checks participation
- [ ] `user_id` from auth, never request body
