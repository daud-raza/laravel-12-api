@extends('layouts.app')

@section('title', 'Task Manager')

@push('head')
    <meta name="api-token" content="{{ $apiToken }}">
    <meta name="me-id" content="{{ $me->id }}">
    @vite(['Modules/TaskManager/resources/assets/css/tasks.css', 'Modules/TaskManager/resources/assets/js/tasks.js'])
@endpush

@section('content')
<div class="tm">
    {{-- ── Sidebar ─────────────────────────────────────────────── --}}
    <aside class="tm-side">
        <header class="tm-side__head">
            <strong>Tasks</strong>
            <div class="tm-side__user">
                <span>{{ $me->name }}</span>
                <form method="POST" action="/logout">@csrf<button class="linkbtn">Log out</button></form>
            </div>
        </header>

        <div class="tm-filter">
            <label>Status
                <select id="filter-status">
                    <option value="">All</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In progress</option>
                    <option value="completed">Completed</option>
                </select>
            </label>
            <label>Priority
                <select id="filter-priority">
                    <option value="">All</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                </select>
            </label>
        </div>

        <section class="tm-panel">
            <div class="tm-panel__head">
                <span>Categories</span>
            </div>
            <ul id="category-list" class="tm-chips"></ul>
            <form id="category-form" class="tm-mini-form">
                <input id="category-name" type="text" placeholder="New category…" maxlength="255" required>
                <input id="category-color" type="color" value="#4f46e5" title="Color">
                <button type="submit">+</button>
            </form>
        </section>

        <section class="tm-panel">
            <div class="tm-panel__head"><span>Tags</span></div>
            <ul id="tag-list" class="tm-chips"></ul>
            <form id="tag-form" class="tm-mini-form">
                <input id="tag-name" type="text" placeholder="New tag…" maxlength="50" required>
                <button type="submit">+</button>
            </form>
        </section>

    </aside>

    {{-- ── Main ────────────────────────────────────────────────── --}}
    <main class="tm-main">
        <div class="tm-toolbar">
            <input id="search" type="search" placeholder="Search tasks by title…" autocomplete="off">
            <button id="new-task-btn" class="btn btn--primary">+ New task</button>
        </div>

        <div id="bulk-bar" class="tm-bulk is-hidden">
            <span id="bulk-count">0 selected</span>
            <select id="bulk-status">
                <option value="">Set status…</option>
                <option value="pending">Pending</option>
                <option value="in_progress">In progress</option>
                <option value="completed">Completed</option>
            </select>
            <button id="bulk-delete" class="btn btn--danger">Delete</button>
            <button id="bulk-clear" class="linkbtn">Clear</button>
        </div>

        <div id="task-list" class="tm-list">
            <p class="tm-empty">Loading…</p>
        </div>

        <div id="pager" class="tm-pager"></div>
    </main>
</div>

{{-- ── Task create / edit modal ────────────────────────────────── --}}
<div id="task-modal" class="modal is-hidden">
    <div class="modal__box">
        <header class="modal__head">
            <strong id="task-modal-title">New task</strong>
            <button class="modal__close" data-close-modal="task-modal">&times;</button>
        </header>
        <form id="task-form" class="modal__body">
            <input type="hidden" id="task-id">
            <label>Title <input type="text" id="task-title" maxlength="255" required></label>
            <label>Description <textarea id="task-description" rows="3"></textarea></label>
            <div class="modal__row">
                <label>Status
                    <select id="task-status">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </label>
                <label>Priority
                    <select id="task-priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </label>
            </div>
            <div class="modal__row">
                <label>Category
                    <select id="task-category"><option value="">— None —</option></select>
                </label>
                <label>Due date <input type="date" id="task-due"></label>
            </div>
            <label class="tm-inline">
                <input type="checkbox" id="task-recurring"> Recurring
            </label>
            <div id="recurrence-fields" class="modal__row is-hidden">
                <label>Repeat
                    <select id="task-recurrence-type">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </label>
                <label>Ends at <input type="date" id="task-recurrence-ends"></label>
            </div>
            <p id="task-form-error" class="auth-error is-hidden"></p>
            <div class="modal__actions">
                <button type="button" class="linkbtn" data-close-modal="task-modal">Cancel</button>
                <button type="submit" class="btn btn--primary">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- ── Task detail drawer ──────────────────────────────────────── --}}
<div id="drawer" class="drawer is-hidden">
    <div class="drawer__scrim" data-close-drawer></div>
    <div class="drawer__panel" id="drawer-panel">
        <p class="tm-empty">Loading…</p>
    </div>
</div>

{{-- ── Toast (undo delete, errors) ─────────────────────────────── --}}
<div id="toast" class="toast is-hidden"></div>
@endsection
