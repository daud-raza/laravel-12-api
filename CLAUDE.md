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
php artisan test tests/Feature/ExampleTest.php

# Run a specific test method
php artisan test --filter=test_method_name

# Code style formatting (Laravel Pint)
./vendor/bin/pint

# Generate JWT secret
php artisan jwt:secret

# Clear caches
php artisan optimize:clear
```

## Architecture

This is a **Laravel 13 REST API** for a task manager application. There are no Blade views — all responses are JSON.

### Request Lifecycle

Routes → `routes/api.php` → Controllers in `app/Http/Controllers/Api/` → Form Request validation → Model operations → JSON response.

All API routes are prefixed with `/api`. Protected routes use `auth:sanctum` middleware.

### Authentication

Sanctum token-based auth. The `User` model also implements `JWTSubject` (tymon/jwt-auth is installed) but JWT is not wired into routes — only Sanctum is active. Tokens are created on register/login via `createToken('auth_token')->plainTextToken` and deleted on logout.

### Authorization

Row-level ownership is enforced manually in controllers by comparing `$resource->user_id === $request->user()->id`, returning 403 on mismatch. There are no Laravel Policies or Gates.

### Models & Relationships

- **User** → hasMany Tasks, hasMany Categories
- **Category** → belongsTo User, hasMany Tasks
- **Task** → belongsTo User, belongsTo Category (nullable); uses `SoftDeletes`

Task status enum: `pending | in_progress | completed`. Priority enum: `low | medium | high`.

### API Response Shape

All controllers return consistent JSON:
```json
{ "message": "...", "data": { ... }, "status_code": 200 }
```

There are no Laravel API Resource classes — controllers return raw arrays/models directly.

### Validation

Form Request classes live in `app/Http/Requests/{Auth,Category,Task}/`. `Store*` requests use `required` rules; `Update*` requests use `sometimes` to allow partial updates.

### Task Filtering

`TaskController@index` accepts query params: `status`, `priority`, `category_id`, `search` (searches task title). Results are paginated at 10 per page.

### Database

MySQL, database name `task_manager`. Categories cascade-delete their tasks when deleted. Tasks set `category_id` to null when a category is deleted. Soft deletes on tasks only.
