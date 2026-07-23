# Chat — Architecture Proposal

> PLAN ONLY. Covers Phase 2 (architecture), Phase 5 (frontend), Phase 6 (real-time). No code.

---

## 1. Module boundaries

The Chat module is a vertical slice inside the existing app namespace (the project is small; a separate package is unnecessary). All chat classes are grouped under `Chat` sub-namespaces so the boundary is obvious and could later be extracted.

```
app/
  Http/
    Controllers/
      Api/Chat/
        ConversationController.php     # JSON API
        MessageController.php
        ChatUserController.php         # user search
      Web/Chat/
        ChatPageController.php         # Blade server-render (hybrid)
    Requests/Chat/
      StoreConversationRequest.php
      StoreMessageRequest.php
      MarkReadRequest.php
    Resources/Chat/
      ConversationResource.php
      MessageResource.php
      ChatUserResource.php
  Models/
    Conversation.php
    Message.php
    (participants via conversation_user pivot — no model needed for MVP)
  Policies/
    ConversationPolicy.php
    MessagePolicy.php
  Services/Chat/
    ChatService.php                    # SHARED business logic (new layer)
  Events/
    MessageSent.php                    # implements ShouldBroadcast
  Listeners/
    (optional) Broadcastable handled by event; notification listener = future
resources/
  views/chat/
    index.blade.php                    # chat shell (list + window)
    partials/conversation-list.blade.php
    partials/message-thread.blade.php
    partials/composer.blade.php
  js/chat/
    chat.js                            # axios calls + Echo subscription
routes/
  api.php                              # + chat API routes
  web.php                              # + chat page routes (MUST be registered first)
  channels.php                         # broadcast channel authorization (new)
```

**Why a `Services/Chat/` layer** (the project has none today): the chosen web strategy renders initial state server-side **and** exposes the same operations over the API. Both entry points must execute identical logic (create-or-find conversation, send message, compute unread). Putting that logic in a `ChatService` means controllers stay thin and there is exactly one implementation — satisfying "no duplicate business logic." Existing non-chat controllers are left untouched; this layer is scoped to Chat.

## 2. Responsibilities

| Layer | Responsibility | Must NOT do |
|-------|----------------|-------------|
| Route | Map URL → controller, attach middleware | Business logic |
| Form Request | Validate shape of input; `authorize()` returns `true` | Ownership checks |
| Policy | Authorize action on a record (participant/sender) | Validation |
| Controller (API) | Call service, return Resource as JSON | Business logic, direct queries |
| Controller (Web) | Call service, return Blade view with data | Duplicate logic (delegates to service) |
| **ChatService** | All business logic: find-or-create conversation, send, mark-read, list, search | HTTP concerns, response formatting |
| Resource | Shape JSON output | Queries |
| Event | `MessageSent` broadcast payload | Persistence |
| Model | Relationships, scopes, casts | Controller logic |

## 3. Request lifecycle (mirrors existing app)

**API send message:**
```
POST /api/conversations/{id}/messages
  → auth:sanctum
  → throttle:chat-send
  → StoreMessageRequest (validate body, client_message_id)
  → ConversationController@... calls $this->authorize('sendMessage', $conversation)  [ConversationPolicy]
  → ChatService::sendMessage() inside DB::transaction
      → persist message, bump conversation.last_message_at, dispatch MessageSent
  → MessageResource → JSON (201)
  (try/catch + Log::error fallback → 500, exactly like existing controllers)
```

**Web (hybrid):**
```
GET /chat  (web middleware, auth)
  → ChatPageController@index
  → ChatService::listConversations($user)   [SAME service the API uses]
  → return view('chat.index', [...])         server-rendered initial list + empty window
Then in the browser:
  → chat.js loads messages + sends via the JSON API (axios)
  → Echo subscribes to private-conversation.{id} for live inbound messages
```

This is the **hybrid**: first paint is server-rendered (fast, SEO-neutral, works without JS for the list), live interaction is API + WebSocket. Both paths funnel through `ChatService`.

## 4. Dependencies

- **Inward:** Chat depends on `User` (existing), Sanctum, the Policy/Resource/Request conventions, the queue, and (for real-time) broadcasting.
- **Outward:** nothing in the existing app depends on Chat. Removing Chat = drop its files + routes + tables. Clean seam.

## 5. Integration points

1. `User` model gains `conversations()` (belongsToMany through pivot) and `messages()` (hasMany).
2. `AppServiceProvider::boot()` registers `Gate::policy(Conversation::class, ConversationPolicy::class)` and `Gate::policy(Message::class, MessagePolicy::class)` — same pattern as the 7 existing policies.
3. `bootstrap/app.php` — **register `routes/web.php` and `routes/channels.php`** (web is currently unregistered). Add the `chat-send` rate limiter in `AppServiceProvider`.
4. `routes/api.php` — append chat endpoints inside the existing `auth:sanctum` group.

---

## 6. Frontend plan (Phase 5) — Blade server-render + API hybrid, no Vue

### Layout
- Reuse/introduce a base layout `resources/views/layouts/app.blade.php` (none exists yet beyond `welcome`). Chat page extends it.
- Two-pane responsive layout: left = conversation list, right = active conversation.

### Screens / partials
- **`chat/index.blade.php`** — shell; server-renders the conversation list (from `ChatService::listConversations`) and an empty/þselected conversation window.
- **`partials/conversation-list.blade.php`** — each row: other user's name, last message snippet, time, unread badge. Server-rendered initially; updated live via JS.
- **`partials/message-thread.blade.php`** — message bubbles; sender vs receiver alignment. Blade `{{ }}` auto-escapes body (XSS defense).
- **`partials/composer.blade.php`** — textarea + send button; disabled when empty.

### States
- **Empty state:** no conversations → prompt "Search a user to start chatting."
- **Loading state:** skeleton rows / spinner while axios fetches older messages.
- **Error state:** inline banner on failed send with a retry button (message stays in composer / marked "failed").
- **Responsive:** desktop = two panes side by side; mobile = list first, tap opens thread full-screen with back button.

### UI flow
1. User opens `/chat` → server-rendered list appears instantly.
2. Click a conversation → `chat.js` fetches messages via `GET /api/conversations/{id}/messages` (cursor), renders thread, calls `POST .../read`.
3. Type + send → optimistic bubble appended with a temp state → `POST .../messages` with a `client_message_id` → on success replace temp with server message; on failure mark "failed" + retry.
4. Inbound message via Echo (`MessageSent`) → append to thread if open, else bump list + unread badge.
5. Infinite scroll up → fetch older page via cursor.

### JS assets
- Existing stack is Vite + axios. Add **Alpine.js** optional for small reactive bits, or plain JS modules in `resources/js/chat/`. Add **Laravel Echo** + a WebSocket client (Reverb/Pusher JS) for real-time. No SPA framework, no Vue.

---

## 7. Real-time strategy (Phase 6)

### Options evaluated

| Approach | Pros | Cons | Cost | Scale | Deploy |
|----------|------|------|------|-------|--------|
| **Laravel Reverb** | First-party (L11+), WebSocket, free, self-host, integrates with Echo/broadcasting | Runs a separate process; you manage scaling | Free (infra only) | Good (horizontal w/ Redis) | `php artisan reverb:start` + supervisor |
| **Pusher (SaaS)** | Zero infra, easy, reliable | Paid beyond free tier; third-party data egress | $$ per messages/connections | Managed | Config only |
| **Ably** | Like Pusher, generous free tier | Third-party dependency | $ | Managed | Config only |
| **Raw WebSockets** | Full control | Reinvents auth/scaling/reconnect | Free | DIY | Heavy |
| **Polling** | Trivial, no infra, works everywhere | Latency + wasted requests | Free | Poor at scale | None |
| **SSE** | Simple server→client stream | One-way; awkward with PHP-FPM workers | Free | Medium | Medium |

### Recommendation
**Reverb as the primary target, polling as the MVP fallback.**

- Ship MVP on **short-interval polling** (`GET /api/conversations` + messages since cursor every N seconds) so the feature works with zero new infra.
- Layer **Reverb** in Phase 5 of the roadmap: `MessageSent implements ShouldBroadcast` on `PrivateChannel("conversation.{id}")`, authorized in `routes/channels.php` via participant check, client subscribes with Echo. Polling stays as automatic fallback if the socket drops.

Rationale: Reverb is the idiomatic Laravel 13 choice, free, and self-hosted (no per-message billing like Pusher). Polling-first keeps the module shippable and testable before committing to the WebSocket process.

### Broadcast optimization
- Broadcast only to the private conversation channel (2 participants), never a global channel.
- Payload = the `MessageResource` shape, so the client needs no extra fetch.
- Dispatch broadcast **after commit** (queue the event) so a rolled-back transaction never emits a phantom message.
