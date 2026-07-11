# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

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

# Run due-date reminder command manually
php artisan tasks:send-due-date-reminders

# Clear caches
php artisan optimize:clear

# Link storage for file attachments
php artisan storage:link
``` 

## Architecture

This is a **Laravel 13 REST API** for a task manager application. There are no Blade views — all responses are JSON.

### Request Lifecycle

`routes/api.php` → Controllers in `app/Http/Controllers/Api/` → Form Request validation → Policy authorization → Model operations → API Resource → JSON response.

All API routes are prefixed with `/api`. Protected routes use the `auth:sanctum` and `throttle:api` middleware.

### API Endpoints

**Public** (rate-limited 10 req/min per IP via `throttle:auth`):
```
POST /api/auth/register
POST /api/auth/login
```

**Protected** (Sanctum + `throttle:api`, 60 req/min per user):
```
POST   /api/auth/logout
GET    /api/auth/me

GET    /api/categories
POST   /api/categories
GET    /api/categories/{id}
PUT    /api/categories/{id}
DELETE /api/categories/{id}

GET    /api/tasks              # filters: status, priority, category_id, search; pinned first, then latest
POST   /api/tasks
GET    /api/tasks/{id}
PUT    /api/tasks/{id}
DELETE /api/tasks/{id}         # soft delete
POST   /api/tasks/bulk         # batch update_status | update_priority | update_category | delete
POST   /api/tasks/{task}/pin   # toggle is_pinned
POST   /api/tasks/{id}/restore

GET    /api/tasks/{task}/comments
POST   /api/tasks/{task}/comments
PUT    /api/comments/{id}      # shallow route
DELETE /api/comments/{id}      # shallow route

GET    /api/tasks/{task}/attachments
POST   /api/tasks/{task}/attachments   # multipart, max 10MB
DELETE /api/tasks/{task}/attachments/{attachment}

GET    /api/tags
POST   /api/tags
DELETE /api/tags/{id}
POST   /api/tasks/{task}/tags  # sync (replaces all tags on a task)

GET    /api/tasks/{task}/subtasks
POST   /api/tasks/{task}/subtasks
PUT    /api/subtasks/{subtask}          # shallow route
DELETE /api/subtasks/{subtask}          # shallow route
PATCH  /api/subtasks/{subtask}/toggle   # toggle is_completed
POST   /api/tasks/{task}/subtasks/reorder  # body: subtask_ids[] in new order

GET    /api/tasks/{task}/time-logs
POST   /api/tasks/{task}/time-logs      # start a timer (no ended_at) or log a completed interval
PATCH  /api/time-logs/{timeLog}/stop    # stop a running timer, computes duration_minutes
DELETE /api/time-logs/{timeLog}
```

**Unauthenticated**:
```
GET /api/health   # {"status":"OK","message":"API is running"}
```

### Authentication

Sanctum token-based auth. Tokens are created on register/login via `createToken('auth_token')->plainTextToken` and deleted on logout. The `User` model also implements `JWTSubject` (tymon/jwt-auth installed) but JWT is not wired into routes.

Both the `api` (60/min, keyed by user id then IP) and `auth` (10/min per IP) rate limiters are defined in `AppServiceProvider::boot()`.

### Providers

There is a single application provider: **`AppServiceProvider`**. Its `boot()` method wires up everything cross-cutting — the `TaskObserver`, `JsonResource::withoutWrapping()`, all policy bindings via `Gate::policy()`, the `TaskCompleted → LogTaskCompleted` event listener, and both rate limiters. (There is no `AuthServiceProvider` or `EventServiceProvider`.)

### Authorization

All resource ownership is enforced via **Laravel Policies** in `app/Policies/`, registered with `Gate::policy()` in `AppServiceProvider`. Controllers call `$this->authorize(...)` — no manual `user_id` checks. For task-nested resources (subtasks, time logs), the `viewAny`/`create` policy methods take the parent `Task` as their second argument (`$this->authorize('create', [Subtask::class, $task])`), while item-level methods walk `->task->user_id`.

**Exception:** `TaskController@bulk` does not go through the policy layer — it scopes ownership with a manual `where('user_id', ...)` on the fetch, and `BulkTaskRequest` validates the `value` per action (enum values for status/priority; for `update_category` the target category must belong to the authenticated user via a scoped `exists` rule).

### Models & Relationships

- **User** → hasMany Tasks, Categories, Tags
- **Category** → belongsTo User, hasMany Tasks; `color` stored as hex string
- **Task** → belongsTo User, belongsTo Category (nullable); hasMany Comments, Attachments, Subtasks, TimeLogs; belongsToMany Tags; uses `SoftDeletes`
  - `status` enum: `pending | in_progress | completed`; `priority` enum: `low | medium | high`
  - `is_pinned` (bool), recurrence fields (`is_recurring`, `recurrence_type` = `daily|weekly|monthly`, `recurrence_ends_at`), `completed_at`
  - Local scopes: `overdue()` (`due_date < today` and not completed), `byPriority(string)`, `forUser(int)`
- **Comment** → belongsTo Task, belongsTo User
- **Tag** → belongsTo User; belongsToMany Tasks (pivot: `tag_task`); `slug` is auto-generated from `name` in the model's `booted()` method
- **Attachment** → belongsTo Task; `AttachmentResource` exposes a public URL via `asset()` — requires `storage:link` to be run
- **Subtask** → belongsTo Task; `title`, `is_completed` (bool), `order` (int); relationship is ordered by `order`
- **TimeLog** → belongsTo Task, belongsTo User; `started_at`, `ended_at` (nullable = running timer), `duration_minutes`, `note`

### Task Observer, Events & Recurrence

`TaskObserver` (registered in `AppServiceProvider`) watches `updated`:
- When `status` changes to `completed` → sets `completed_at` (via `saveQuietly`, timestamps disabled), fires `TaskCompleted`, and — if the task `is_recurring` with a `recurrence_type` and `due_date` — creates the **next occurrence** (a fresh `pending` task with the due date advanced by the recurrence interval, unless it would fall past `recurrence_ends_at`).
- When `status` changes away from `completed` → clears `completed_at`.

Because `bulk` `update_status` calls `$task->update(...)`, completing tasks in bulk also triggers the observer (completion events + recurrence).

`TaskCompleted` is listened to by `LogTaskCompleted`, which writes to the Laravel log. The listener is registered via `Event::listen()` in `AppServiceProvider`.

### API Resources

All responses go through Resource classes in `app/Http/Resources/`. `JsonResource::withoutWrapping()` is called in `AppServiceProvider`, so there is no top-level `data` wrapper on single resources. Collections still use a `data` key.

`TaskResource` exposes a computed `is_overdue` boolean (`due_date` before today and not completed — consistent with the `overdue()` scope) and a `total_time_minutes` sum, and uses `whenLoaded` on all relationships to avoid N+1 queries. `index` eager-loads `category` and `tags` only; `show` additionally loads `comments.user`, `attachments`, `subtasks`, and `timeLogs`, so nested collections appear only on the detail endpoint.

### Validation

Form Request classes live in `app/Http/Requests/{Auth,Category,Task,Comment,Tag,Subtask,TimeLog}/`. `Store*` requests use `required` rules; `Update*` requests use `sometimes` for partial updates. Key rules: comment `body` max 2000 chars; tag `name` max 50 chars; `due_date` must be `after_or_equal:today`. `BulkTaskRequest` derives its `value` rules from the chosen `action`.

### Task Filtering & Pagination

`TaskController@index` accepts: `status`, `priority`, `category_id`, `search` (title LIKE). Results are paginated at 10 per page; response includes a `meta` key with `current_page`, `last_page`, `total`.

### Queue & Mail

`SendWelcomeMail` job (implements `ShouldQueue`) is dispatched on registration and sends a markdown welcome email. Configure `QUEUE_CONNECTION` and `MAIL_*` in `.env`. In testing, `QUEUE_CONNECTION=sync` and `MAIL_MAILER=array` are set in `phpunit.xml`.

### Scheduler

`tasks:send-due-date-reminders` finds non-completed tasks due tomorrow and logs reminders. Runs daily at 08:00 via the Console Kernel scheduler. Add `* * * * * php /path/to/artisan schedule:run` to crontab to activate it.

### Soft Delete Restore

`POST /api/tasks/{id}/restore` restores a soft-deleted task. Uses `Task::withTrashed()->findOrFail($id)` and the `restore` policy gate.

### Database

MySQL, database name `task_manager`. Key cascade rules:
- Category deleted → tasks `category_id` set to null
- User deleted → tasks and categories cascade-deleted
- Task deleted → comments and attachments cascade-deleted (soft delete only on tasks)

File attachments are stored in `storage/app/attachments/task-{id}/` via `Storage::disk('local')`.

### Testing

Feature tests use `RefreshDatabase` and are in `tests/Feature/` (one per subsystem: Auth, Category, Task, Comment, Attachment, Tag, Subtask, TimeLog, Health, ReminderCommand). Recurrence logic has a unit test in `tests/Unit/TaskRecurrenceTest.php`. Factories exist for all models: `User`, `Task`, `Category`, `Comment`, `Attachment`, `Tag`, `Subtask`, `TimeLog`. Tests use `actingAs($user)` for Sanctum auth — no token header needed in tests.

**Local environment note:** the project targets **PHP 8.3/8.4** (Laravel 13). The suite will not boot on older PHP — a machine pinned to PHP 8.1 cannot `composer install` the locked dependencies or run `php artisan test`.
