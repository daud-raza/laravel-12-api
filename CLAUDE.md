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

All API routes are prefixed with `/api`. Protected routes use `auth:sanctum` middleware.

### API Endpoints

**Public** (rate-limited 10 req/min per IP via `throttle:auth`):
```
POST /api/auth/register
POST /api/auth/login
```

**Protected** (Sanctum, 60 req/min):
```
POST   /api/auth/logout
GET    /api/auth/me

GET    /api/categories
POST   /api/categories
GET    /api/categories/{id}
PUT    /api/categories/{id}
DELETE /api/categories/{id}

GET    /api/tasks              # filters: status, priority, category_id, search
POST   /api/tasks
GET    /api/tasks/{id}
PUT    /api/tasks/{id}
DELETE /api/tasks/{id}         # soft delete
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
```

**Unauthenticated**:
```
GET /api/health   # {"status":"OK","message":"API is running"}
```

### Authentication

Sanctum token-based auth. Tokens are created on register/login via `createToken('auth_token')->plainTextToken` and deleted on logout. The `User` model also implements `JWTSubject` (tymon/jwt-auth installed) but JWT is not wired into routes.

The `auth` rate limiter is defined in `AppServiceProvider::configureRateLimiting()`.

### Authorization

All resource ownership is enforced via **Laravel Policies** in `app/Policies/`. Controllers call `$this->authorize(...)` — no manual `user_id` checks. Policies are registered in `AuthServiceProvider::$policies`.

### Models & Relationships

- **User** → hasMany Tasks, Categories, Tags
- **Category** → belongsTo User, hasMany Tasks; `color` stored as hex string
- **Task** → belongsTo User, belongsTo Category (nullable); hasMany Comments, Attachments; belongsToMany Tags; uses `SoftDeletes`
  - `status` enum: `pending | in_progress | completed`; `priority` enum: `low | medium | high`
  - Local scopes: `overdue()`, `byPriority(string)`, `forUser(int)`
- **Comment** → belongsTo Task, belongsTo User
- **Tag** → belongsTo User; belongsToMany Tasks (pivot: `tag_task`); `slug` is auto-generated from `name` in the model's `booted()` method
- **Attachment** → belongsTo Task; `AttachmentResource` exposes a public URL via `asset()` — requires `storage:link` to be run

### Task Observer & Events

`TaskObserver` (registered in `AppServiceProvider`) watches `updated`:
- When `status` changes to `completed` → sets `completed_at` timestamp (via `saveQuietly`) and fires `TaskCompleted` event.
- When `status` changes away from `completed` → clears `completed_at`.

`TaskCompleted` event is listened to by `LogTaskCompleted`, which writes to the Laravel log. Registered in `EventServiceProvider`.

### API Resources

All responses go through Resource classes in `app/Http/Resources/`. `JsonResource::withoutWrapping()` is called in `AppServiceProvider`, so there is no top-level `data` wrapper on single resources. Collections still use a `data` key.

`TaskResource` exposes a computed `is_overdue` boolean and uses conditional eager-loaded relationships to avoid N+1 queries. Controllers pass `->with(['category', 'tags', 'comments.user', 'attachments'])` on show/index.

### Validation

Form Request classes live in `app/Http/Requests/{Auth,Category,Task,Comment,Tag}/`. `Store*` requests use `required` rules; `Update*` requests use `sometimes` for partial updates. Key rules: comment `body` max 2000 chars; tag `name` max 50 chars; `due_date` must be `after_or_equal:today`.

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

Feature tests use `RefreshDatabase` and are in `tests/Feature/`. Factories exist for `User`, `Task`, and `Category`. Tests use `actingAs($user)` for Sanctum auth — no token header needed in tests.
