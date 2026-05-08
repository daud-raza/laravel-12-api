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

### Authentication

Sanctum token-based auth. Tokens are created on register/login via `createToken('auth_token')->plainTextToken` and deleted on logout. The `User` model also implements `JWTSubject` (tymon/jwt-auth installed) but JWT is not wired into routes.

Auth endpoints are rate-limited to 10 req/min per IP via the `throttle:auth` middleware. The `auth` limiter is defined in `AppServiceProvider::configureRateLimiting()`.

### Authorization

All resource ownership is enforced via **Laravel Policies** in `app/Policies/`. Controllers call `$this->authorize(...)` — no manual `user_id` checks. Policies are registered in `AuthServiceProvider::$policies`.

### Models & Relationships

- **User** → hasMany Tasks, Categories, Tags
- **Category** → belongsTo User, hasMany Tasks
- **Task** → belongsTo User, belongsTo Category (nullable); hasMany Comments, Attachments; belongsToMany Tags; uses `SoftDeletes`
- **Comment** → belongsTo Task, belongsTo User
- **Tag** → belongsTo User; belongsToMany Tasks (pivot: `tag_task`)
- **Attachment** → belongsTo Task

Task `status` enum: `pending | in_progress | completed`. `priority` enum: `low | medium | high`.

### Task Observer & Events

`TaskObserver` (registered in `AppServiceProvider`) watches `updated`:
- When `status` changes to `completed` → sets `completed_at` timestamp and fires `TaskCompleted` event.
- When `status` changes away from `completed` → clears `completed_at`.

`TaskCompleted` event is listened to by `LogTaskCompleted`, which writes to the Laravel log.

### API Resources

All responses go through Resource classes in `app/Http/Resources/`. `JsonResource::withoutWrapping()` is called in `AppServiceProvider`, so there is no top-level `data` wrapper on single resources. Collections still use a `data` key.

`TaskResource` exposes a computed `is_overdue` boolean field.

### Validation

Form Request classes live in `app/Http/Requests/{Auth,Category,Task,Comment,Tag}/`. `Store*` requests use `required` rules; `Update*` requests use `sometimes` for partial updates.

### Task Filtering & Pagination

`TaskController@index` accepts: `status`, `priority`, `category_id`, `search` (title LIKE). Results are paginated at 10 per page; response includes a `meta` key with `current_page`, `last_page`, `total`.

### Queue & Mail

`SendWelcomeMail` job is dispatched on registration. Configure `QUEUE_CONNECTION` and `MAIL_*` in `.env`. In testing, `QUEUE_CONNECTION=sync` and `MAIL_MAILER=array` are set in `phpunit.xml`.

### Scheduler

`tasks:send-due-date-reminders` runs daily at 08:00 via the Console Kernel scheduler. Add `* * * * * php /path/to/artisan schedule:run` to crontab to activate it.

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
