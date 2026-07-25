# Feature — Task Manager (Core Module)

> **Status:** BUILT / REFERENCE — this describes code that exists and is verifiable in the repo. (Contrast with [chat.md](chat.md), which is PLAN ONLY.)
> Part of the Laravel 13 Task Manager API. The database schema lives in its own doc: [task-manager-database-design.md](task-manager-database-design.md). The exhaustive end-to-end reference (slim-structure migration notes, version history, every file path) is [PROJECT_BRAIN.md](PROJECT_BRAIN.md).
>
> Last verified against the codebase: **Laravel 13.8.0 / PHP 8.3–8.4**.

---

## Table of Contents

1. [Summary](#1-summary)
2. [Goals](#2-goals)
3. [Out of scope / Non-Goals](#3-out-of-scope--non-goals)
4. [Why this design](#4-why-this-design)
5. [Key design decisions](#5-key-design-decisions)
6. [Integration points / structure](#6-integration-points--structure)
7. [Risks, known gaps & technical debt](#7-risks-known-gaps--technical-debt)
8. [Architecture](#8-architecture)
9. [API Design](#9-api-design)
10. [Security](#10-security)
11. [Performance](#11-performance)
12. [Edge Cases](#12-edge-cases)
13. [Testing & roadmap](#13-testing--roadmap)
14. [Future enhancements](#14-future-enhancements)

---

## 1. Summary

A **REST API for a task manager**, built on **Laravel 13**. Every endpoint returns **JSON** — there are no Blade views. It is a single-user-scoped system: every resource (task, category, tag, comment, attachment, subtask, time log) belongs to the authenticated user, and ownership is strictly enforced through Laravel Policies.

Users create **Tasks**, organize them into **Categories**, label them with **Tags**, break them into **Subtasks**, discuss them via **Comments**, attach **files**, and track effort with **Time Logs**. Tasks support **priorities**, **statuses**, **due dates**, **pinning**, **soft delete/restore**, **bulk actions**, and **recurrence** (auto-regenerating tasks).

## 2. Goals

- Full CRUD for tasks with filtering (status, priority, category, title search), pagination, and pinned-first ordering.
- Organize tasks by category and many-to-many tags.
- Rich task detail: comments, file attachments, ordered subtasks with progress, and time tracking.
- Bulk operations over many tasks in one request (status / priority / category / delete).
- Recurrence: completing a recurring task auto-creates its next occurrence.
- Soft delete with restore.
- Token auth (Sanctum), a queued welcome email, and a daily due-date reminder command.

## 3. Out of scope / Non-Goals

- No web UI (JSON only; the welcome email is the sole Blade/markdown view).
- No multi-user collaboration / assignment / teams — every resource is owned by one user.
- JWT (`tymon/jwt-auth`) is installed and `User` implements `JWTSubject`, but **JWT is not wired into any route**; Sanctum is the active mechanism.
- The reminder command **only logs** — it does not yet send mail/notifications.

## 4. Why this design

The app uses the **Laravel 11+ slim skeleton** (inherited by 13): no `Http/Kernel.php`, `Console/Kernel.php`, `Exceptions/Handler.php`, or the four extra service providers. Everything cross-cutting is wired fluently in `bootstrap/app.php` and in the single `AppServiceProvider::boot()`. See [PROJECT_BRAIN.md §3](PROJECT_BRAIN.md#3-laravel-13-slim-structure) for the full old-vs-new migration table and version-to-version differences.

Every feature is built from the same uniform lifecycle, so a new endpoint is predictable to add and review:

```
routes/api.php
  → auth:sanctum + throttle middleware
  → Form Request (validation; authorize() returns true)
  → Controller (Api/*)
  → Policy authorization ($this->authorize(...))
  → DB::transaction { Model operations }
  → Eloquent Observer / Event (side effects)
  → API Resource (JSON shaping, whenLoaded)
  → JsonResponse
```

## 5. Key design decisions

| Decision | Choice | Why |
|----------|--------|-----|
| Auth | Sanctum personal access tokens | Simple, stateless bearer tokens; created on register/login, deleted on logout |
| Ownership | One Policy per model, `$user->id === $model->user_id` | No manual `user_id` checks scattered in controllers |
| Nested-resource authz | Policy receives the parent task (`authorize('create', [Subtask::class, $task])`) | Parent-scoped create/viewAny; item methods walk `->task->user_id` |
| Response shape | `JsonResource::withoutWrapping()` | Single resources are bare; collections keep `data` + `meta` |
| Writes | Wrapped in `DB::transaction` + uniform try/catch → `Log::error` + 500 | Consistent, atomic, observable |
| Completion side-effects | `TaskObserver` on `updated` | Sets `completed_at`, fires `TaskCompleted`, spawns recurrence — no controller clutter |
| Recurrence | Lazy: next occurrence created **on completion**, not scheduled ahead | Simple, no cron dependency; chain continues as tasks are completed |
| Bulk | Manual `where('user_id', …)` re-scope (not the Policy layer) | Batch efficiency; `BulkTaskRequest` validates `value` per action, incl. user-scoped category |
| Slug | Auto-generated in `Tag::booted()` | Callers never set `slug` |

## 6. Integration points / structure

Everything cross-cutting is registered in **`AppServiceProvider::boot()`** (the only provider):

- **Observer:** `Task::observe(TaskObserver::class)`.
- **Resources:** `JsonResource::withoutWrapping()`.
- **Policies:** `Gate::policy()` for Task, Category, Comment, Tag, Attachment, Subtask, TimeLog.
- **Events:** `Event::listen(TaskCompleted::class, LogTaskCompleted::class)`.
- **Rate limiters:** `api` (60/min, keyed by user id then IP) and `auth` (10/min per IP, custom 429 JSON).

`bootstrap/app.php` registers only `api` + `commands` routing (no `web`/`channels`), sets `apiPrefix: 'api'`, aliases `throttle`, and renders API exceptions as JSON (401/403/404/422). The scheduler lives in `routes/console.php` (`Schedule::command('tasks:send-due-date-reminders')->dailyAt('08:00')`).

## 7. Risks, known gaps & technical debt

| Severity | Item |
|----------|------|
| High | Historically **no feature tests** for Subtasks/TimeLogs/Bulk/Pin/Recurrence and **no factories** beyond User/Task/Category. (The repo now has per-subsystem tests and factories for all models — keep this in sync if it regresses.) |
| Medium | `tasks:send-due-date-reminders` **only logs** — no real email/notification. |
| Medium | JWT installed + `JWTSubject` implemented but **not wired into routes** — dead capability or future migration. |
| Low | Subtask `reorder` validates `exists:subtasks,id` globally (not scoped to the task), though the update itself is scoped via the task relationship. |
| Low | No pagination on comments/tags/subtasks/time-logs (only tasks are paginated). |

> **Fixed on the current branch:** `is_overdue` now matches the `overdue()` scope (due *before* today, not "today"); `throttle:api` (60/min) is now actually applied to the protected route group; `BulkTaskRequest` now validates `value` per action and confirms an `update_category` target belongs to the user.

## 8. Architecture

### 8.1 Layer responsibilities

| Layer | Responsibility | Must NOT do |
|-------|----------------|-------------|
| Route (`routes/api.php`) | Map URL → controller, attach `auth:sanctum` / `throttle` | Business logic |
| Form Request | Validate input shape; `authorize()` returns `true` | Ownership checks |
| Policy | Authorize action on a record (`$user->id === $model->user_id`) | Validation |
| Controller (`Api/*`) | Authorize, run writes in `DB::transaction`, return a Resource | Cross-request logic; no service layer exists |
| Observer / Event / Listener | Side-effects of completion (timestamp, event, recurrence) | HTTP concerns |
| Resource | Shape JSON, `whenLoaded()` relations | Queries |
| Model | Relationships, scopes, casts, `booted()` hooks | Controller logic |

There is **no service layer** — controllers are the orchestration point (contrast with the planned Chat module, which introduces a shared `ChatService`).

### 8.2 Models & relationships

```
User ──< Task, Category, Tag        (hasMany)
Category >── User ; Category ──< Task
Task >── User ; Task >── Category (nullable)
Task ──< Comment (latest()) ; Task <──> Tag (pivot tag_task, withTimestamps)
Task ──< Attachment ; Task ──< Subtask (orderBy order) ; Task ──< TimeLog (latest())
Task uses SoftDeletes
Comment >── Task, User ; Tag >── User ; Attachment >── Task ; Subtask >── Task ; TimeLog >── Task, User
```

**Model logic worth knowing**
- **`Task`** — casts `due_date`/`recurrence_ends_at`→date, `completed_at`→datetime, `is_pinned`/`is_recurring`→bool. Scopes: `overdue()` (`due_date < today AND status != completed`), `byPriority()`, `forUser()`.
- **`Tag`** — `booted()` auto-generates `slug` from `name` on create.
- **`User`** — implements `JWTSubject`; `password` cast `hashed`; hides `password`/`remember_token`.

Full column-level detail and the ER diagram are in [task-manager-database-design.md](task-manager-database-design.md).

### 8.3 Completion, recurrence & events (`TaskObserver`)

On Task `updated`:
- **status changed → `completed`:** set `completed_at = now()` via `saveQuietly()` (no observer re-loop, timestamps disabled) → dispatch `TaskCompleted` → `createNextRecurrence()`.
- **status changed away from `completed`:** clear `completed_at`.

`createNextRecurrence()` runs only when `is_recurring && recurrence_type && due_date`. It advances the due date (`addDay`/`addWeek`/`addMonth`); if `recurrence_ends_at` is set and the next date passes it, it stops; otherwise it creates a brand-new `pending` task copying the recurrence settings. Because bulk `update_status` calls `$task->update(...)`, bulk completion triggers the observer too (events + recurrence).

`TaskCompleted` → `LogTaskCompleted` writes an info log line (task id, title, user, completed_at).

## 9. API Design

All routes prefixed `/api`. **39 routes total.** Single Task responses are bare `TaskResource`; most other endpoints wrap under a descriptive key; task lists use `data` + `meta`.

### Unauthenticated
```
GET  /api/health                              → {"status":"OK","message":"API is running"}
```

### Public — `throttle:auth`, 10/min per IP
```
POST /api/auth/register
POST /api/auth/login
```

### Protected — `auth:sanctum` + `throttle:api`, 60/min per user
```
POST   /api/auth/logout
GET    /api/auth/me

# Categories (apiResource)
GET|POST /api/categories ; GET|PUT|DELETE /api/categories/{category}

# Tasks (extras declared BEFORE the apiResource wildcard)
POST   /api/tasks/bulk                        # update_status | update_priority | update_category | delete
POST   /api/tasks/{task}/pin                  # toggle is_pinned
POST   /api/tasks/{id}/restore                # restore soft-deleted
GET    /api/tasks                             # filters: status, priority, category_id, search; pinned first, then latest; 10/page
POST   /api/tasks
GET|PUT|DELETE /api/tasks/{task}              # DELETE = soft delete

# Comments (nested list/create + shallow update/delete)
GET|POST /api/tasks/{task}/comments
PUT|DELETE /api/comments/{comment}            # shallow

# Attachments
GET|POST /api/tasks/{task}/attachments        # POST: multipart, file max 10MB
DELETE /api/tasks/{task}/attachments/{attachment}

# Tags
GET|POST /api/tags ; DELETE /api/tags/{tag}
POST   /api/tasks/{task}/tags                 # sync — replaces all tags on the task

# Subtasks
GET|POST /api/tasks/{task}/subtasks
PUT|DELETE /api/subtasks/{subtask}            # shallow
PATCH  /api/subtasks/{subtask}/toggle         # flip is_completed
POST   /api/tasks/{task}/subtasks/reorder     # body: subtask_ids[] in new order

# Time Tracking
GET|POST /api/tasks/{task}/time-logs          # POST: start a timer OR log a finished span
PATCH  /api/time-logs/{timeLog}/stop          # stop a running timer, computes duration_minutes
DELETE /api/time-logs/{timeLog}
```

> **Route ordering:** `tasks/bulk`, `tasks/{task}/pin`, `tasks/{id}/restore` are declared **before** `apiResource('tasks')` so literal segments win over the `{task}` wildcard.

### Feature deep-dives (by code path)

- **Auth** — register validates → creates user (`Hash::make`) → dispatches `SendWelcomeMail` → issues Sanctum token (`201`). login uses `Auth::attempt` (`401` on bad creds). logout deletes only the current token. me returns `UserResource`.
- **Task index** — starts from `$request->user()->tasks()` (already scoped), eager-loads `category`+`tags`, orders `is_pinned DESC` then `latest()`, applies filters, paginates 10/page with a `meta` block.
- **Task show** — `authorize('view')` then eager-loads everything (`category, tags, comments.user, attachments, subtasks, timeLogs`) — nested collections appear only here.
- **Pin** — toggles `is_pinned`; pinned tasks float to the top of index.
- **Bulk** — `BulkTaskRequest` validates `task_ids`, `action`, and per-action `value`; controller re-scopes `where('user_id', $userId)` so foreign IDs can't be mutated; a `match($action)` runs per task in a transaction.
- **Restore** — `Task::withTrashed()->findOrFail($id)`, `authorize('restore')`, `$task->restore()`; unknown id → friendly 404.
- **Categories** — user-scoped CRUD; index/show expose a task count via `withCount`/`loadCount`.
- **Tags & sync** — CRUD limited to index/store/destroy; `sync` validates `tag_ids.*` with `exists:tags,id,user_id,{userId}` then `$task->tags()->sync(...)` (replaces all).
- **Comments** — to view/create you must own the task (`authorize('view', $task)`); to update/delete you must own the comment (`CommentPolicy`).
- **Attachments** — `authorize('update', $task)`, `file` max 10MB, stored to `attachments/task-{id}/` on the `local` disk; `AttachmentResource` exposes a URL via `asset()` (needs `storage:link`); destroy removes the file **and** row in one transaction.
- **Subtasks** — index returns `data` + `meta` (`total`, `completed`); store auto-assigns `order = max+1`; toggle flips `is_completed`; reorder writes array index back as `order`.
- **Time tracking** — store starts a timer (no `ended_at`) or logs a finished span (computes `duration_minutes`); stop sets `ended_at`/duration (422 if already stopped); index returns `total_minutes`.

## 10. Security

- **Authentication:** Sanctum bearer tokens on every protected route; missing/invalid → global `401 {"message":"Unauthenticated."}`.
- **Authorization:** one Policy per model, `$user->id === $model->user_id`; nested resources authorize against the parent task. Denials render as `403 {"message":"Forbidden."}`.
- **Mass assignment:** explicit `$fillable`; `user_id` is always set from the relationship/`auth()`, never from request body.
- **Ownership on bulk & sync:** bulk re-scopes to the user; tag sync requires each tag to belong to the user (`exists` scoped rule); bulk `update_category` requires the target category to belong to the user.
- **Rate limiting:** `auth` 10/min per IP (register/login), `api` 60/min per user (all protected routes).
- **Input bounds:** comment `body` max 2000; tag `name` max 50; attachment `file` max 10MB; `due_date` `after_or_equal:today`; `recurrence_type` `required_if:is_recurring,true`.
- **Passwords:** `hashed` cast + `Hash::make`; never returned (hidden on `User`).

## 11. Performance

- **N+1 avoidance:** `TaskResource` uses `whenLoaded()` for every relationship; controllers eager-load exactly what each endpoint needs (index loads `category`+`tags` only; show loads the rest).
- **Pagination:** tasks paginate 10/page with a `meta` block; other collections are unpaginated (small per-task sets).
- **Ordering:** index sorts `is_pinned DESC, created_at DESC` — both cheap on indexed/pk columns.
- **Transactions:** each write is a single `DB::transaction`; the observer's `saveQuietly()` avoids a second full save cycle on completion.
- **Computed fields:** `is_overdue` and `total_time_minutes` are derived in the resource from already-loaded data — no extra queries.

## 12. Edge Cases

| # | Edge case | Handling |
|---|-----------|----------|
| 1 | Completing an already-completed task | Observer keys off `wasChanged('status')`; no change → no duplicate event/recurrence. |
| 2 | Re-completing (completed → pending → completed) | Each genuine transition to `completed` fires again — **spawns another recurrence occurrence each time** (known behavior). |
| 3 | Recurrence past `recurrence_ends_at` | Next due date computed; if it exceeds `recurrence_ends_at`, no new task is created (series ends). |
| 4 | Recurrence with no `due_date` / not recurring | `createNextRecurrence()` guard returns early — nothing spawned. |
| 5 | Bulk with foreign task IDs | Re-scoped `where('user_id', …)`; foreign IDs silently excluded; empty match → 404. |
| 6 | Bulk `update_category` to another user's category | `BulkTaskRequest` scoped `exists` rule → 422. |
| 7 | Deleting a category with tasks | DB `nullOnDelete` sets `tasks.category_id = null`; tasks survive. |
| 8 | Task due **today** | `is_overdue = false` (overdue means strictly before today), matching the `overdue()` scope and the reminder command. |
| 9 | Stopping an already-stopped timer | 422 "This timer has already been stopped." |
| 10 | Restore an unknown/purged task id | `ModelNotFoundException` → friendly 404. |
| 11 | Soft-deleted task's children | Comments/attachments/subtasks/time_logs remain in DB but are unreachable via the trashed task; hard delete cascades them. |
| 12 | Unauthorized access to another user's resource | Policy denies → 403 (global handler). |

## 13. Testing & roadmap

- Feature tests in `tests/Feature/` (one per subsystem: Auth, Category, Task, Comment, Attachment, Tag, Subtask, TimeLog, Health, ReminderCommand) using `RefreshDatabase` and `actingAs($user)` — no token header needed.
- Recurrence has a unit test in `tests/Unit/TaskRecurrenceTest.php`.
- Factories exist for all models.
- **Local environment note:** the suite targets PHP 8.3/8.4; it will not boot on PHP 8.1 (`composer install` fails on the locked deps; a stale `vendor/` crashes on `Application::configure`).

Commands:
```bash
composer install
php artisan migrate
php artisan storage:link            # required for attachment URLs
php artisan test
php artisan tasks:send-due-date-reminders
./vendor/bin/pint
```

## 14. Future enhancements

Email notifications (make the reminder actually send) · activity log / audit trail via the existing Observer/Event wiring · team / workspace multi-tenancy + assignment · real-time notifications (Broadcasting + Reverb) · task dependencies · task templates (clone) · RBAC · full-text search (Scout) · CSV/PDF export · OAuth social login · API versioning · signed webhooks on completion. See [PROJECT_BRAIN.md §12](PROJECT_BRAIN.md#12-future-feature-ideas) for the ranked list.
