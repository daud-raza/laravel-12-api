# PROJECT BRAIN — Laravel 13 Task Manager API

> **Purpose of this document**
> This is the single source of truth for understanding this project end-to-end. It is written so that any new developer (or AI assistant) can read it once and become productive immediately — understanding not just *what* exists, but *how* it works and *why* it is built this way.
>
> Last verified against the codebase: **Laravel 13.8.0 / PHP 8.4.20**.

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Tech Stack & Packages](#2-tech-stack--packages)
3. [Laravel 13 Slim Structure](#3-laravel-13-slim-structure)
4. [Setup & Commands](#4-setup--commands)
5. [Database Schema](#5-database-schema)
6. [Models & Relationships](#6-models--relationships)
7. [Complete API Reference](#7-complete-api-reference)
8. [Feature Deep Dives](#8-feature-deep-dives)
9. [Cross-Cutting Patterns & Conventions](#9-cross-cutting-patterns--conventions)
10. [Testing](#10-testing)
11. [Known Gaps & Technical Debt](#11-known-gaps--technical-debt)
12. [Future Feature Ideas](#12-future-feature-ideas)

---

## 1. Project Overview

This is a **REST API for a task manager application**, built on **Laravel 13**. There are **no Blade views** — every endpoint returns **JSON**. It is a single-user-scoped system: every resource (task, category, tag, etc.) belongs to the authenticated user, and ownership is strictly enforced through Laravel Policies.

**Core domain:** users create **Tasks**, organize them into **Categories** and label them with **Tags**, break them into **Subtasks**, discuss them via **Comments**, attach **files**, and track effort with **Time Logs**. Tasks support **priorities**, **statuses**, **due dates**, **pinning**, **soft deletes/restore**, and **recurrence** (auto-regenerating tasks).

**Request lifecycle:**

```
routes/api.php
  → auth:sanctum middleware (protected routes)
  → Form Request (validation)
  → Controller (Api/*)
  → Policy authorization ($this->authorize(...))
  → DB::transaction { Model operations }
  → Eloquent Observer / Event (side effects)
  → API Resource (JSON shaping)
  → JsonResponse
```

---

## 2. Tech Stack & Packages

| Concern              | Choice                                              |
|----------------------|-----------------------------------------------------|
| Framework            | Laravel 13.8.0                                       |
| Language             | PHP 8.3 / 8.4 (`"php": "^8.3 || ^8.4"`)              |
| Database             | MySQL — database name `task_manager`                |
| Auth                 | Laravel Sanctum 4 (token-based)                     |
| JWT                  | `tymon/jwt-auth` 2.2 — **installed but NOT wired into routes** |
| HTTP client          | Guzzle 7.9                                           |
| Code style           | Laravel Pint                                         |
| Tests                | PHPUnit 11.5                                         |
| Local dev            | Laravel Sail, Tinker, Collision                     |

**Key fact about JWT:** The `User` model implements `Tymon\JWTAuth\Contracts\JWTSubject` (`getJWTIdentifier()`, `getJWTCustomClaims()`), but all routes use `auth:sanctum`. JWT is a future option, not the active auth mechanism.

---

## 3. Laravel 13 Slim Structure

This project uses the **Laravel 11+ slim application skeleton**. The old `app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`, and the separate `Auth/Event/Route/Broadcast` service providers **do not exist**. Everything is configured fluently.

### `bootstrap/app.php` — the heart of bootstrapping

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: TrustProxies::class);
        $middleware->alias([
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // JSON-friendly renderers for API exceptions (see below)
    })
    ->create();
```

### API exception handling (in `withExceptions`)

All exceptions are rendered as JSON for `api/*` requests:

| Exception                     | HTTP | Body                                            |
|-------------------------------|------|-------------------------------------------------|
| `AuthenticationException`     | 401  | `{"message": "Unauthenticated."}`               |
| `AccessDeniedHttpException`   | 403  | `{"message": "Forbidden."}`                     |
| `ModelNotFoundException`      | 404  | `{"message": "<Model> not found."}`             |
| `NotFoundHttpException`       | 404  | `{"message": "Route not found."}`               |
| `ValidationException`         | 422  | `{"message": "Validation failed.", "errors": {...}}` |

### `app/Providers/AppServiceProvider.php` — the *only* provider

Its `boot()` method does **all** the wiring that used to be split across multiple providers:

- **Observer:** `Task::observe(TaskObserver::class)`
- **API resource wrapping:** `JsonResource::withoutWrapping()` — removes the top-level `data` wrapper on **single** resources (collections still get `data`)
- **Policies** via `Gate::policy(Model::class, Policy::class)` for: Task, Category, Comment, Tag, Attachment, Subtask, TimeLog
- **Events** via `Event::listen(TaskCompleted::class, LogTaskCompleted::class)`
- **Rate limiters:**
  - `api` → 60/min, keyed by user id (or IP if guest)
  - `auth` → 10/min, keyed by IP, returns a custom 429 JSON message

### `routes/console.php` — scheduler lives here now

```php
Schedule::command('tasks:send-due-date-reminders')->dailyAt('08:00');
```

There is no `Console/Kernel.php`. The `Schedule` facade is used directly in `routes/console.php`.

### `config/app.php`

The `providers` array contains only `App\Providers\AppServiceProvider::class` (merged onto `ServiceProvider::defaultProviders()`).

### 3.1 What changed in this project (old skeleton → slim)

These are the concrete structural changes this codebase adopted when migrating off the Laravel 9/10 skeleton. Every item below is verifiable in the listed file.

| Concept | Old way (Laravel ≤10) | This project (11+ slim) | File |
|---------|----------------------|-------------------------|------|
| App bootstrap | `app.php` returned `new Application`, plus 3 Kernel files | One fluent `Application::configure()->withRouting()->withMiddleware()->withExceptions()->create()` | `bootstrap/app.php` |
| HTTP Kernel | `app/Http/Kernel.php` listed middleware groups | **Deleted** — middleware in `->withMiddleware()` | (removed) |
| Console Kernel | `app/Console/Kernel.php` held the scheduler | **Deleted** — scheduler in `routes/console.php` via `Schedule::command()` | `routes/console.php` |
| Exception Handler | `app/Exceptions/Handler.php` class | **Deleted** — handled in `->withExceptions()` closure | `bootstrap/app.php` |
| Policy registration | `AuthServiceProvider::$policies` array | `Gate::policy(Model::class, Policy::class)` | `app/Providers/AppServiceProvider.php` |
| Event registration | `EventServiceProvider::$listen` array | `Event::listen(Event::class, Listener::class)` | `app/Providers/AppServiceProvider.php` |
| Service providers | 5 (Auth, Event, Route, Broadcast, App) | **Only `AppServiceProvider`** — other 4 deleted | `config/app.php` |

### 3.2 Version-to-version differences (10 → 11 → 12 → 13)

> **Key fact:** the slim structure above debuted in **Laravel 11**, not 13. Versions 12 and 13 kept the same architecture. There is **no feature in this project unique to 13 and absent from 11/12** — they share the same skeleton. This project is "version 13" because of its framework dependency (`^13.0`) and PHP floor, not because of 13-only features.

- **Laravel 10 → 11 (the big leap):** introduced the slim skeleton (everything in §3.1); minimum PHP 8.2; per-second rate limiting; built-in health route; casts definable as a `casts()` method.
- **Laravel 11 → 12 (minor):** no new structural features — maintenance, dependency bumps, new starter kits (React/Vue/Livewire).
- **Laravel 12 → 13 (this project):** same slim structure as 11; **minimum PHP 8.3** (this project requires `^8.3 || ^8.4`); Carbon 3 and updated Symfony components; internal refactors. An evolution, not a reinvention.

**Accurate one-liner for interviews/docs:** *"Uses the slim application structure (single `bootstrap/app.php`, no Kernel/Handler files, `Gate::policy()` / `Event::listen()` registration, scheduler in `routes/console.php`) — introduced in Laravel 11 and inherited by 13 — and requires PHP 8.3+, which is the hard requirement Laravel 13 specifically raised."*

---

## 4. Setup & Commands

```bash
# Install dependencies
composer install

# Run development server
php artisan serve

# Run migrations
php artisan migrate

# Run all tests
php artisan test

# Run a single test file
php artisan test tests/Feature/AuthTest.php

# Run a specific test method
php artisan test --filter=test_user_can_register

# Code style formatting (Laravel Pint)
./vendor/bin/pint

# Run the due-date reminder command manually
php artisan tasks:send-due-date-reminders

# Clear caches
php artisan optimize:clear

# Link storage for file attachments (REQUIRED for attachment public URLs)
php artisan storage:link
```

**Activating the scheduler in production** — add to crontab:

```
* * * * * php /path/to/artisan schedule:run
```

**Environment notes:**
- Configure `QUEUE_CONNECTION` and `MAIL_*` in `.env` for the welcome email job.
- In testing (`phpunit.xml`): `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`.

---

## 5. Database Schema

All custom migrations are dated `2026_05_*`. Below is every table with its columns and cascade behavior.

### `users` (Laravel default)
`id`, `name`, `email` (unique), `password` (hashed), `email_verified_at`, `remember_token`, timestamps.

### `categories`
| Column      | Type     | Notes                                   |
|-------------|----------|-----------------------------------------|
| id          | bigint   | PK                                      |
| user_id     | FK→users | **cascadeOnDelete** (user deleted → categories deleted) |
| name        | string   |                                         |
| color       | string   | default `#000000` (hex)                 |
| timestamps  |          |                                         |

### `tasks`
| Column             | Type            | Notes                                          |
|--------------------|-----------------|------------------------------------------------|
| id                 | bigint          | PK                                             |
| user_id            | FK→users        | **cascadeOnDelete**                            |
| category_id        | FK→categories   | nullable, **nullOnDelete** (category deleted → task.category_id = null) |
| title              | string          |                                                |
| description        | text            | nullable                                       |
| status             | enum            | `pending` \| `in_progress` \| `completed` (default `pending`) |
| priority           | enum            | `low` \| `medium` \| `high` (default `medium`) |
| is_pinned          | boolean         | default false (added in later migration)       |
| is_recurring       | boolean         | default false                                  |
| recurrence_type    | enum            | `daily` \| `weekly` \| `monthly`, nullable     |
| recurrence_ends_at | date            | nullable                                       |
| due_date           | date            | nullable                                       |
| completed_at       | timestamp       | nullable (set by observer)                     |
| deleted_at         | timestamp       | **softDeletes**                                |
| timestamps         |                 |                                                |

### `comments`
`id`, `task_id` (FK→tasks, cascadeOnDelete), `user_id` (FK→users, cascadeOnDelete), `body` (text), timestamps.

### `tags`
`id`, `user_id` (FK→users, cascadeOnDelete), `name` (string), `slug` (string, **unique**), timestamps.

### `tag_task` (pivot)
`tag_id` (FK→tags, cascadeOnDelete), `task_id` (FK→tasks, cascadeOnDelete), **composite PK** `[tag_id, task_id]`, timestamps.

### `attachments`
`id`, `task_id` (FK→tasks, cascadeOnDelete), `original_name` (string), `path` (string), `size` (unsignedBigInteger, bytes), `mime_type` (string), timestamps.

### `subtasks`
`id`, `task_id` (FK→tasks, cascadeOnDelete), `title` (string), `is_completed` (boolean, default false), `order` (unsignedSmallInteger, default 0), timestamps.

### `time_logs`
`id`, `task_id` (FK→tasks, cascadeOnDelete), `user_id` (FK→users, cascadeOnDelete), `started_at` (timestamp), `ended_at` (timestamp, nullable), `duration_minutes` (unsignedInteger, nullable), `note` (string, nullable), timestamps.

### Cascade rules summary
- **User deleted** → tasks, categories, tags cascade-deleted.
- **Category deleted** → tasks' `category_id` set to null (task survives).
- **Task deleted (soft)** → comments, attachments, subtasks, time_logs remain in DB but are inaccessible through the soft-deleted task; a hard delete cascades them.
- Only **tasks** use soft deletes.

---

## 6. Models & Relationships

```
User ──< Task        (hasMany)
User ──< Category    (hasMany)
User ──< Tag         (hasMany)

Category ──< Task    (hasMany);  Category >── User (belongsTo)

Task >── User        (belongsTo)
Task >── Category    (belongsTo, nullable)
Task ──< Comment     (hasMany, latest())
Task <──> Tag        (belongsToMany, pivot tag_task, withTimestamps)
Task ──< Attachment  (hasMany)
Task ──< Subtask     (hasMany, orderBy('order'))
Task ──< TimeLog     (hasMany, latest())
Task uses SoftDeletes

Comment >── Task, >── User
Tag >── User; Tag <──> Task
Attachment >── Task
Subtask >── Task
TimeLog >── Task, >── User
```

### Model-specific logic worth knowing

**`Task`** (`app/Models/Task.php`)
- Casts: `due_date`→date, `completed_at`→datetime, `recurrence_ends_at`→date, `is_pinned`/`is_recurring`→boolean.
- **Query scopes:**
  - `scopeOverdue()` — `due_date < today AND status != completed`
  - `scopeByPriority(string $priority)`
  - `scopeForUser(int $userId)`
- `comments()` and `timeLogs()` are ordered `latest()`; `subtasks()` ordered by `order`.

**`Tag`** (`app/Models/Tag.php`)
- `booted()` hook: on `creating`, `slug` is auto-generated from `name` via `Str::slug()`. **You never set slug manually.**

**`Subtask`** — `is_completed` cast to boolean.

**`TimeLog`** — `started_at` / `ended_at` cast to datetime.

**`User`** — implements `JWTSubject` (see §2). `password` cast `hashed`. `hidden`: password, remember_token.

---

## 7. Complete API Reference

All routes are prefixed `/api`. **39 routes total.**

### Unauthenticated
```
GET    /api/health                         → {"status":"OK","message":"API is running"}
```

### Public — rate-limited 10/min per IP (`throttle:auth`)
```
POST   /api/auth/register
POST   /api/auth/login
```

### Protected — `auth:sanctum`, 60/min
```
POST   /api/auth/logout
GET    /api/auth/me

# Categories (apiResource)
GET    /api/categories
POST   /api/categories
GET    /api/categories/{category}
PUT    /api/categories/{category}
DELETE /api/categories/{category}

# Tasks (apiResource + extras)
POST   /api/tasks/bulk                      # bulk update/delete
POST   /api/tasks/{task}/pin                # toggle pin
POST   /api/tasks/{id}/restore              # restore soft-deleted
GET    /api/tasks                           # filters: status, priority, category_id, search
POST   /api/tasks
GET    /api/tasks/{task}
PUT    /api/tasks/{task}
DELETE /api/tasks/{task}                    # soft delete

# Comments (nested + shallow)
GET    /api/tasks/{task}/comments
POST   /api/tasks/{task}/comments
PUT    /api/comments/{comment}              # shallow
DELETE /api/comments/{comment}              # shallow

# Attachments
GET    /api/tasks/{task}/attachments
POST   /api/tasks/{task}/attachments        # multipart, file max 10MB
DELETE /api/tasks/{task}/attachments/{attachment}

# Tags
GET    /api/tags
POST   /api/tags
DELETE /api/tags/{tag}
POST   /api/tasks/{task}/tags               # sync (replaces all tags on a task)

# Subtasks
GET    /api/tasks/{task}/subtasks
POST   /api/tasks/{task}/subtasks
PUT    /api/subtasks/{subtask}
DELETE /api/subtasks/{subtask}
PATCH  /api/subtasks/{subtask}/toggle       # flip is_completed
POST   /api/tasks/{task}/subtasks/reorder   # reorder by id array

# Time Tracking
GET    /api/tasks/{task}/time-logs
POST   /api/tasks/{task}/time-logs          # start timer OR log a finished span
PATCH  /api/time-logs/{timeLog}/stop        # stop a running timer
DELETE /api/time-logs/{timeLog}
```

> **Route ordering note:** `tasks/bulk`, `tasks/{task}/pin`, and `tasks/{id}/restore` are declared **before** `apiResource('tasks')` so the literal/static segments are matched before the `{task}` wildcard.

---

## 8. Feature Deep Dives

Each feature is described by its real code path so you can trace and modify it confidently.

### 8.1 Authentication (`AuthController`)
- **register** → `RegisterRequest` validates → `DB::transaction` creates user with `Hash::make` → dispatches `SendWelcomeMail` job → issues Sanctum token via `createToken('auth_token')->plainTextToken` → returns `201` with `user` + `token`.
- **login** → `LoginRequest` → `Auth::attempt()`; on failure returns `401` with a generic message; on success issues a fresh token.
- **logout** → deletes the **current** access token only (`currentAccessToken()->delete()`).
- **me** → returns the authenticated user via `UserResource`.
- Every method is wrapped in try/catch with `Log::error` and a `500` fallback.

### 8.2 Task CRUD (`TaskController`)
- **index** — starts from `$request->user()->tasks()` (already user-scoped), eager-loads `category` + `tags`, orders `is_pinned DESC` then `latest()`. Optional filters: `status`, `priority`, `category_id`, `search` (title `LIKE %term%`). Paginated **10/page**; response has `data` (resource collection) + `meta` (`current_page`, `last_page`, `total`).
- **store** — `StoreTaskRequest` → creates via the user relationship (so `user_id` is automatic) → returns bare `TaskResource` (no wrapper) with `201`.
- **show** — `authorize('view')` → eager-loads everything (`category, tags, comments.user, attachments, subtasks, timeLogs`) → bare `TaskResource`.
- **update** — `authorize('update')` → updates inside transaction. Changing `status` to/from `completed` triggers the **TaskObserver** (see 8.5).
- **destroy** — `authorize('delete')` → **soft delete**.

### 8.3 Pinning (`TaskController@pin`)
`POST /api/tasks/{task}/pin` toggles `is_pinned`. Pinned tasks float to the top of `index` because of `orderByDesc('is_pinned')`.

### 8.4 Bulk Actions (`TaskController@bulk`)
`POST /api/tasks/bulk` with `{ task_ids: [...], action, value }`.
- `BulkTaskRequest` validates: `task_ids` (array, exists), `action` ∈ `update_status|update_priority|update_category|delete`, `value` required unless action is `delete`.
- Controller **re-scopes** to `where('user_id', $userId)` — so a user can never mutate another user's tasks even if they pass foreign IDs.
- A `match($action)` runs the operation per task inside a transaction.

### 8.5 Task Completion, Recurrence & Events (`TaskObserver`)
On Task `updated`:
- If `status` **changed to** `completed`: set `completed_at = now()` via `saveQuietly()` (no extra observer loop, timestamps disabled), dispatch `TaskCompleted` event, and call `createNextRecurrence()`.
- If `status` **changed away from** `completed`: clear `completed_at`.

`createNextRecurrence()` — only if `is_recurring && recurrence_type && due_date`. Computes the next `due_date` (`addDay`/`addWeek`/`addMonth`). If `recurrence_ends_at` is set and the next date passes it, it stops. Otherwise it **creates a brand-new pending task** copying the recurrence settings.

`TaskCompleted` event → `LogTaskCompleted` listener → writes an info log line (task id, title, user, completed_at). Registered via `Event::listen` in `AppServiceProvider`.

### 8.6 Soft Delete & Restore (`TaskController@restore`)
`POST /api/tasks/{id}/restore` — uses `Task::withTrashed()->findOrFail($id)`, `authorize('restore')`, then `$task->restore()`. A permanently-deleted/unknown id returns a friendly `404`.

### 8.7 Categories (`CategoryController`)
Standard CRUD, user-scoped. `index` and `show` include `withCount('tasks')` / `loadCount('tasks')` so the resource can expose a task count. Deleting a category sets its tasks' `category_id` to null (DB-level `nullOnDelete`).

### 8.8 Tags & Sync (`TagController`)
- CRUD limited to `index`, `store`, `destroy` (no update/show).
- `slug` is auto-generated in the model.
- **sync** (`POST /api/tasks/{task}/tags`) — `authorize('update', $task)`, validates `tag_ids.*` with `exists:tags,id,user_id,{userId}` (a tag must belong to the current user), then `$task->tags()->sync($request->tag_ids)` — **replaces** all tags on the task. Returns the new tag set.

### 8.9 Comments (`CommentController`)
- Nested + shallow routing: list/create are under `/tasks/{task}/comments`; update/delete are flat `/comments/{comment}`.
- Authorization quirk: to **view or create** a comment you must pass `authorize('view', $task)` (i.e. own the task). To **update/delete** a comment you must pass the `CommentPolicy` (own the comment).
- `store` sets `user_id` from the authenticated user, not from input.

### 8.10 Attachments (`AttachmentController`)
- Upload (`store`) — `authorize('update', $task)`, validates `file` (required, max **10240 KB = 10MB**), stores to `attachments/task-{id}/` on the **`local`** disk, persists `original_name`, `path`, `size`, `mime_type`.
- `AttachmentResource` exposes a public URL via `asset()` — **requires `php artisan storage:link`**.
- `destroy` — deletes the physical file from storage **and** the DB row inside one transaction.

### 8.11 Subtasks (`SubtaskController`)
- `index` returns `data` + `meta` (`total`, `completed` count).
- `store` — auto-assigns `order = max(order)+1` unless an `order` is supplied.
- `toggle` (`PATCH .../toggle`) — flips `is_completed`.
- `reorder` (`POST .../reorder`) — takes `subtask_ids[]` and writes the array index back as each subtask's `order`, all in one transaction.
- Authorization uses the policy with the parent task: `authorize('create', [Subtask::class, $task])` etc.

### 8.12 Time Tracking (`TimeLogController`)
- `store` — two modes:
  - **Start a timer:** no `ended_at` → `started_at` defaults to `now()`, `duration_minutes` null, message "Timer started successfully".
  - **Log a finished span:** `ended_at` provided → `duration_minutes` computed from the start/end diff, message "Time log created successfully".
- `stop` (`PATCH .../stop`) — sets `ended_at = now()` and computes `duration_minutes`; returns `422` if already stopped.
- `index` — returns logs + `total_minutes` (sum).
- `TaskResource` also surfaces `total_time_minutes` when timeLogs are eager-loaded.

### 8.13 Welcome Email Queue (`SendWelcomeMail` job → `WelcomeMail`)
Dispatched on registration. Implements `ShouldQueue` (uses the `Queueable` trait). Sends a markdown mailable (`emails.welcome`) to the new user. Runs synchronously in tests (`QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`).

### 8.14 Scheduler (`SendDueDateReminders` command)
- Signature `tasks:send-due-date-reminders` (declared with PHP attributes `#[Signature]` / `#[Description]`).
- Finds non-completed tasks whose `due_date` is **tomorrow**, eager-loads `user`, and **logs a reminder line per task** to the console output.
- **It only logs** — it does not yet send mail/notifications (see Technical Debt).
- Scheduled daily at 08:00 in `routes/console.php`.

---

## 9. Cross-Cutting Patterns & Conventions

These conventions are applied **consistently** across every controller. Follow them when adding features.

### Validation — Form Requests
- Located in `app/Http/Requests/{Auth,Category,Task,Comment,Tag,Subtask,TimeLog}/`.
- `Store*` requests use `required`; `Update*` requests use `sometimes` for partial updates.
- All `authorize()` return `true` — **ownership is enforced by Policies in the controller, not in the request.**
- Custom human-readable `messages()` are provided.
- Key rules: comment `body` max 2000; tag `name` max 50; task `due_date` must be `after_or_equal:today`; `recurrence_type` `required_if:is_recurring,true`; attachment `file` max 10MB.

### Authorization — Policies
- One policy per model in `app/Policies/`, registered via `Gate::policy()` in `AppServiceProvider`.
- Controllers call `$this->authorize('ability', $model)` — **no manual `user_id` checks scattered in controllers.**
- Ownership check is uniformly `$user->id === $model->user_id`.
- For child resources (Subtask/TimeLog), the policy receives the parent task: `authorize('create', [Subtask::class, $task])`.

### API Resources
- One resource per model in `app/Http/Resources/`.
- `JsonResource::withoutWrapping()` is global → **single resources have no `data` wrapper**; collections still do.
- `TaskResource` computes `is_overdue` and uses `whenLoaded()` for every relationship to **avoid N+1** — controllers explicitly eager-load what each endpoint needs.

### Transactions & Error Handling — the universal controller shape
Every write is wrapped in `DB::transaction(...)`. Every controller method follows this pattern:

```php
try {
    $this->authorize('ability', $model);          // when ownership applies
    $result = DB::transaction(fn () => /* writes */);
    return response()->json([...], 2xx);
} catch (AuthorizationException) {
    return response()->json(['message' => 'You do not have permission ...'], 403);
} catch (\Throwable $e) {
    Log::error('Failed to ...', ['error' => $e]);
    return response()->json(['message' => 'Something went wrong ...'], 500);
}
```

### Response shapes
- **Single Task** (`store`/`show`/`update`): bare `TaskResource` (no wrapper).
- **Most other resources**: wrapped in a descriptive key — `{"message": "...", "category": {...}}` / `"categories": [...]` / `"tags"` / `"comments"` / `"attachments"` / `"subtask(s)"` / `"time_log(s)"`.
- **Paginated lists** (tasks): `{ message, data: [...], meta: { current_page, last_page, total } }`.

### Rate limiting
- `api`: 60/min, keyed by user id or IP.
- `auth`: 10/min by IP with a custom 429 JSON message. Applied to register/login via `throttle:auth`.

### Observers / Events / Jobs
- `TaskObserver` registered in `AppServiceProvider::boot()`.
- `TaskCompleted` → `LogTaskCompleted` via `Event::listen`.
- `SendWelcomeMail` is a queued job; configure `QUEUE_CONNECTION`.

---

## 10. Testing

- Feature tests in `tests/Feature/` use the `RefreshDatabase` trait.
- Existing tests: `AuthTest`, `CategoryTest`, `TaskTest` (plus `ExampleTest`).
- Factories exist for **User, Task, Category** only.
- Tests authenticate with `actingAs($user)` — **no token header needed** in tests.

Run:
```bash
php artisan test                                   # all
php artisan test tests/Feature/AuthTest.php        # one file
php artisan test --filter=test_user_can_register   # one method
```

---

## 11. Known Gaps & Technical Debt

| Severity | Item                                                                                  |
|----------|---------------------------------------------------------------------------------------|
| High     | **No feature tests** for Subtasks, TimeLogs, Bulk actions, Pin, or Recurrence.        |
| High     | **No factories** for Comment, Tag, Attachment, Subtask, TimeLog — limits test setup.  |
| Medium   | `tasks:send-due-date-reminders` **only logs** — no real email/notification is sent.   |
| Medium   | JWT (`tymon/jwt-auth`) is installed and `User` implements `JWTSubject`, but **JWT is not wired into any route** — dead capability or future migration. |
| Low      | `reorder` validates `exists:subtasks,id` globally (not scoped to the task), though the update is scoped via the task relationship. |
| Low      | No pagination on comments/tags/subtasks/time-logs (only tasks are paginated).         |

---

## 12. Future Feature Ideas

Ordered roughly by learning value / architectural impact:

1. **Email notifications** — make `SendDueDateReminders` actually dispatch queued mail/notifications (closes a known gap; teaches Mailables + queue workers + retries).
2. **Activity log / audit trail** — `morphMany` activities recording who changed what (leverages the Observer/Event wiring already present).
3. **Team / workspace support** — multi-tenancy, task assignment to teammates, scoping queries by team.
4. **Real-time notifications** — Laravel Broadcasting + Reverb (WebSockets) for task assignment/completion.
5. **Task dependencies** — "Task B blocked until Task A done" (pivot table + graph queries).
6. **Task templates** — save a task + subtasks as a reusable template (model cloning).
7. **RBAC** — admin/manager/user roles (Spatie Permission or custom Gates).
8. **Full-text search** — replace `LIKE` with Scout + Meilisearch/Algolia.
9. **Export** — tasks to CSV/PDF (streamed, memory-efficient).
10. **OAuth / social login** — Laravel Socialite (Google/GitHub).
11. **API versioning** — `/api/v2/` alongside v1.
12. **Webhooks** — notify external services on task completion (HMAC-signed, retried).

---

> **When you add a feature**, update this document: add a migration entry to §5, a relationship to §6, the endpoint(s) to §7, a deep-dive to §8, and remove the item from §11/§12 if it closes a gap. Keep this brain in sync — it is what the next person reads first.
