import axios from 'axios';

/**
 * Task Manager front-end (hybrid): the page shell is server-rendered by Blade,
 * all data is driven through the JSON API with a Sanctum bearer token embedded
 * in a <meta> tag. No framework — plain DOM.
 */

const token = document.querySelector('meta[name="api-token"]')?.content;
const meId = parseInt(document.querySelector('meta[name="me-id"]')?.content, 10);

const api = axios.create({ baseURL: '/api' });
api.defaults.headers.common['Accept'] = 'application/json';
if (token) api.defaults.headers.common['Authorization'] = `Bearer ${token}`;

// ── Small helpers ───────────────────────────────────────────────────────────
const $ = (sel) => document.querySelector(sel);
const el = (tag, cls, text) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
};
const esc = (s) => (s == null ? '' : String(s));

let toastTimer = null;
function toast(message, { actionLabel, onAction, error = false } = {}) {
    const box = $('#toast');
    box.innerHTML = '';
    box.classList.toggle('toast--error', error);
    box.append(el('span', null, message));
    if (actionLabel) {
        const btn = el('button', 'toast__action', actionLabel);
        btn.addEventListener('click', () => { box.classList.add('is-hidden'); onAction?.(); });
        box.append(btn);
    }
    box.classList.remove('is-hidden');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => box.classList.add('is-hidden'), actionLabel ? 6000 : 3000);
}

function apiError(err, fallback) {
    const msg = err?.response?.data?.message || fallback || 'Something went wrong.';
    toast(msg, { error: true });
}

// ── State ─────────────────────────────────────────────────────────────────
const state = {
    filters: { status: '', priority: '', category_id: '', search: '' },
    page: 1,
    lastPage: 1,
    categories: [],
    tags: [],
    selected: new Set(),
};

// ── Categories ──────────────────────────────────────────────────────────────
async function loadCategories() {
    try {
        const { data } = await api.get('/categories');
        state.categories = data.categories || [];
        renderCategories();
        fillCategorySelects();
    } catch (e) { apiError(e, 'Failed to load categories.'); }
}

function renderCategories() {
    const list = $('#category-list');
    list.innerHTML = '';
    if (!state.categories.length) {
        list.append(el('li', 'tm-chips__empty', 'No categories yet'));
        return;
    }
    state.categories.forEach((c) => {
        const li = el('li', 'chip');
        if (state.filters.category_id === String(c.id)) li.classList.add('is-active');
        const dot = el('span', 'chip__dot');
        dot.style.background = c.color || '#9ca3af';
        li.append(dot, el('span', 'chip__label', `${c.name} (${c.tasks_count ?? 0})`));
        li.addEventListener('click', () => {
            state.filters.category_id = state.filters.category_id === String(c.id) ? '' : String(c.id);
            renderCategories();
            reload();
        });
        const del = el('button', 'chip__x', '×');
        del.title = 'Delete category';
        del.addEventListener('click', async (ev) => {
            ev.stopPropagation();
            if (!confirm(`Delete category "${c.name}"? Tasks keep existing but lose this category.`)) return;
            try {
                await api.delete(`/categories/${c.id}`);
                if (state.filters.category_id === String(c.id)) state.filters.category_id = '';
                await loadCategories();
                reload();
            } catch (e) { apiError(e, 'Failed to delete category.'); }
        });
        li.append(del);
        list.append(li);
    });
}

function fillCategorySelects() {
    const sel = $('#task-category');
    const current = sel.value;
    sel.innerHTML = '<option value="">— None —</option>';
    state.categories.forEach((c) => {
        const o = el('option', null, c.name);
        o.value = c.id;
        sel.append(o);
    });
    sel.value = current;
}

$('#category-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = $('#category-name').value.trim();
    if (!name) return;
    try {
        await api.post('/categories', { name, color: $('#category-color').value });
        $('#category-name').value = '';
        await loadCategories();
    } catch (err) { apiError(err, 'Failed to create category.'); }
});

// ── Tags ──────────────────────────────────────────────────────────────────
async function loadTags() {
    try {
        const { data } = await api.get('/tags');
        state.tags = data.tags || [];
        renderTags();
    } catch (e) { apiError(e, 'Failed to load tags.'); }
}

function renderTags() {
    const list = $('#tag-list');
    list.innerHTML = '';
    if (!state.tags.length) {
        list.append(el('li', 'tm-chips__empty', 'No tags yet'));
        return;
    }
    state.tags.forEach((t) => {
        const li = el('li', 'chip chip--tag');
        li.append(el('span', 'chip__label', `#${t.name}`));
        const del = el('button', 'chip__x', '×');
        del.title = 'Delete tag';
        del.addEventListener('click', async (ev) => {
            ev.stopPropagation();
            if (!confirm(`Delete tag "${t.name}"?`)) return;
            try {
                await api.delete(`/tags/${t.id}`);
                await loadTags();
            } catch (e) { apiError(e, 'Failed to delete tag.'); }
        });
        li.append(del);
        list.append(li);
    });
}

$('#tag-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = $('#tag-name').value.trim();
    if (!name) return;
    try {
        await api.post('/tags', { name });
        $('#tag-name').value = '';
        await loadTags();
    } catch (err) { apiError(err, 'Failed to create tag.'); }
});

// ── Task list ───────────────────────────────────────────────────────────────
async function reload() {
    const listEl = $('#task-list');
    listEl.innerHTML = '<p class="tm-empty">Loading…</p>';
    const params = { page: state.page };
    Object.entries(state.filters).forEach(([k, v]) => { if (v) params[k] = v; });
    try {
        const { data } = await api.get('/tasks', { params });
        state.lastPage = data.meta?.last_page || 1;
        renderTasks(data.data || []);
        renderPager();
    } catch (e) {
        listEl.innerHTML = '';
        apiError(e, 'Failed to load tasks.');
    }
}

function renderTasks(tasks) {
    const listEl = $('#task-list');
    listEl.innerHTML = '';
    if (!tasks.length) {
        listEl.append(el('p', 'tm-empty', 'No tasks match. Create one with “+ New task”.'));
        updateBulkBar();
        return;
    }
    tasks.forEach((t) => listEl.append(taskRow(t)));
    updateBulkBar();
}

function taskRow(t) {
    const row = el('div', 'task');
    if (t.status === 'completed') row.classList.add('task--done');
    if (t.is_overdue) row.classList.add('task--overdue');

    const check = el('input', 'task__select');
    check.type = 'checkbox';
    check.checked = state.selected.has(t.id);
    check.addEventListener('change', () => {
        check.checked ? state.selected.add(t.id) : state.selected.delete(t.id);
        updateBulkBar();
    });

    const main = el('div', 'task__main');
    const titleRow = el('div', 'task__titlerow');
    if (t.is_pinned) titleRow.append(el('span', 'task__pin', '📌'));
    titleRow.append(el('span', 'task__title', t.title));
    main.append(titleRow);

    const meta = el('div', 'task__meta');
    meta.append(badge(t.priority, `pri pri--${t.priority}`));
    meta.append(badge(t.status.replace('_', ' '), `st st--${t.status}`));
    if (t.category) {
        const cat = el('span', 'task__cat', t.category.name);
        cat.style.borderColor = t.category.color || '#d1d5db';
        meta.append(cat);
    }
    if (t.due_date) meta.append(el('span', t.is_overdue ? 'task__due is-overdue' : 'task__due', `due ${t.due_date}`));
    if (t.is_recurring) meta.append(el('span', 'task__recur', `↻ ${t.recurrence_type}`));
    (t.tags || []).forEach((tag) => meta.append(el('span', 'task__tag', `#${tag.name}`)));
    main.append(meta);

    main.addEventListener('click', () => openDrawer(t.id));

    const actions = el('div', 'task__actions');
    const pin = el('button', 'iconbtn', t.is_pinned ? 'Unpin' : 'Pin');
    pin.addEventListener('click', (e) => { e.stopPropagation(); pinTask(t.id); });
    const del = el('button', 'iconbtn iconbtn--danger', 'Delete');
    del.addEventListener('click', (e) => { e.stopPropagation(); deleteTask(t.id, t.title); });
    actions.append(pin, del);

    row.append(check, main, actions);
    return row;
}

function badge(text, cls) { return el('span', `badge ${cls}`, text); }

function renderPager() {
    const pager = $('#pager');
    pager.innerHTML = '';
    if (state.lastPage <= 1) return;
    const prev = el('button', 'btn', 'Prev');
    prev.disabled = state.page <= 1;
    prev.addEventListener('click', () => { state.page--; reload(); });
    const info = el('span', 'tm-pager__info', `Page ${state.page} / ${state.lastPage}`);
    const next = el('button', 'btn', 'Next');
    next.disabled = state.page >= state.lastPage;
    next.addEventListener('click', () => { state.page++; reload(); });
    pager.append(prev, info, next);
}

async function pinTask(id) {
    try { await api.post(`/tasks/${id}/pin`); reload(); }
    catch (e) { apiError(e, 'Failed to pin task.'); }
}

async function deleteTask(id, title) {
    try {
        await api.delete(`/tasks/${id}`);
        state.selected.delete(id);
        reload();
        toast(`Deleted “${title}”.`, {
            actionLabel: 'Undo',
            onAction: async () => {
                try { await api.post(`/tasks/${id}/restore`); reload(); toast('Task restored.'); }
                catch (e) { apiError(e, 'Failed to restore task.'); }
            },
        });
    } catch (e) { apiError(e, 'Failed to delete task.'); }
}

// ── Filters / search ────────────────────────────────────────────────────────
$('#filter-status').addEventListener('change', (e) => { state.filters.status = e.target.value; state.page = 1; reload(); });
$('#filter-priority').addEventListener('change', (e) => { state.filters.priority = e.target.value; state.page = 1; reload(); });

let searchTimer = null;
$('#search').addEventListener('input', (e) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        state.filters.search = e.target.value.trim();
        state.page = 1;
        reload();
    }, 300);
});

// ── Bulk actions ────────────────────────────────────────────────────────────
function updateBulkBar() {
    const bar = $('#bulk-bar');
    const n = state.selected.size;
    if (!n) { bar.classList.add('is-hidden'); return; }
    bar.classList.remove('is-hidden');
    $('#bulk-count').textContent = `${n} selected`;
}

$('#bulk-status').addEventListener('change', async (e) => {
    const value = e.target.value;
    if (!value || !state.selected.size) { e.target.value = ''; return; }
    await bulk('update_status', value);
    e.target.value = '';
});
$('#bulk-delete').addEventListener('click', () => {
    if (!state.selected.size) return;
    if (!confirm(`Delete ${state.selected.size} task(s)?`)) return;
    bulk('delete');
});
$('#bulk-clear').addEventListener('click', () => { state.selected.clear(); reload(); });

async function bulk(action, value) {
    try {
        const body = { task_ids: [...state.selected], action };
        if (action !== 'delete') body.value = value;
        const { data } = await api.post('/tasks/bulk', body);
        state.selected.clear();
        reload();
        toast(data.message || 'Bulk action done.');
    } catch (e) { apiError(e, 'Bulk action failed.'); }
}

// ── Task create / edit modal ──────────────────────────────────────────────────
const taskModal = $('#task-modal');

function openModal(id) { document.getElementById(id).classList.remove('is-hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('is-hidden'); }

document.querySelectorAll('[data-close-modal]').forEach((b) =>
    b.addEventListener('click', () => closeModal(b.dataset.closeModal)));

$('#task-recurring').addEventListener('change', (e) => {
    $('#recurrence-fields').classList.toggle('is-hidden', !e.target.checked);
});

function resetTaskForm() {
    $('#task-id').value = '';
    $('#task-title').value = '';
    $('#task-description').value = '';
    $('#task-status').value = 'pending';
    $('#task-priority').value = 'medium';
    $('#task-category').value = '';
    $('#task-due').value = '';
    $('#task-recurring').checked = false;
    $('#task-recurrence-type').value = 'daily';
    $('#task-recurrence-ends').value = '';
    $('#recurrence-fields').classList.add('is-hidden');
    $('#task-form-error').classList.add('is-hidden');
}

$('#new-task-btn').addEventListener('click', () => {
    resetTaskForm();
    $('#task-modal-title').textContent = 'New task';
    openModal('task-modal');
    $('#task-title').focus();
});

function fillTaskForm(t) {
    resetTaskForm();
    $('#task-modal-title').textContent = 'Edit task';
    $('#task-id').value = t.id;
    $('#task-title').value = t.title || '';
    $('#task-description').value = t.description || '';
    $('#task-status').value = t.status;
    $('#task-priority').value = t.priority;
    $('#task-category').value = t.category?.id || '';
    $('#task-due').value = t.due_date || '';
    $('#task-recurring').checked = !!t.is_recurring;
    if (t.is_recurring) {
        $('#recurrence-fields').classList.remove('is-hidden');
        $('#task-recurrence-type').value = t.recurrence_type || 'daily';
        $('#task-recurrence-ends').value = t.recurrence_ends_at || '';
    }
}

$('#task-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = $('#task-id').value;
    const body = {
        title: $('#task-title').value.trim(),
        description: $('#task-description').value.trim() || null,
        status: $('#task-status').value,
        priority: $('#task-priority').value,
        category_id: $('#task-category').value || null,
        due_date: $('#task-due').value || null,
        is_recurring: $('#task-recurring').checked,
        recurrence_type: $('#task-recurring').checked ? $('#task-recurrence-type').value : null,
        recurrence_ends_at: $('#task-recurring').checked ? ($('#task-recurrence-ends').value || null) : null,
    };
    const errBox = $('#task-form-error');
    errBox.classList.add('is-hidden');
    try {
        if (id) await api.put(`/tasks/${id}`, body);
        else await api.post('/tasks', body);
        closeModal('task-modal');
        await loadCategories();
        reload();
        toast(id ? 'Task updated.' : 'Task created.');
    } catch (err) {
        const errs = err?.response?.data?.errors;
        errBox.textContent = errs ? Object.values(errs).flat().join(' ')
            : (err?.response?.data?.message || 'Failed to save task.');
        errBox.classList.remove('is-hidden');
    }
});

// ── Detail drawer ─────────────────────────────────────────────────────────────
const drawer = $('#drawer');
let currentTask = null;

document.querySelector('[data-close-drawer]').addEventListener('click', closeDrawer);
function closeDrawer() { drawer.classList.add('is-hidden'); currentTask = null; }

async function openDrawer(id) {
    drawer.classList.remove('is-hidden');
    $('#drawer-panel').innerHTML = '<p class="tm-empty">Loading…</p>';
    try {
        const { data } = await api.get(`/tasks/${id}`);
        currentTask = data;
        renderDrawer(data);
    } catch (e) { apiError(e, 'Failed to load task.'); closeDrawer(); }
}

function renderDrawer(t) {
    const p = $('#drawer-panel');
    p.innerHTML = '';

    const head = el('header', 'drawer__head');
    head.append(el('strong', null, t.title));
    const close = el('button', 'modal__close', '×');
    close.addEventListener('click', closeDrawer);
    head.append(close);
    p.append(head);

    const sub = el('div', 'drawer__sub');
    sub.append(badge(t.priority, `pri pri--${t.priority}`));
    sub.append(badge(t.status.replace('_', ' '), `st st--${t.status}`));
    if (t.category) sub.append(el('span', 'task__cat', t.category.name));
    if (t.due_date) sub.append(el('span', 'task__due', `due ${t.due_date}`));
    if (t.total_time_minutes) sub.append(el('span', 'task__cat', `⏱ ${t.total_time_minutes} min`));
    p.append(sub);

    if (t.description) p.append(el('p', 'drawer__desc', t.description));

    const editBtn = el('button', 'btn', 'Edit task');
    editBtn.addEventListener('click', () => { fillTaskForm(t); openModal('task-modal'); });
    p.append(editBtn);

    p.append(tagsSection(t));
    p.append(subtasksSection(t));
    p.append(commentsSection(t));
    p.append(attachmentsSection(t));
    p.append(timeLogsSection(t));
}

function sectionShell(title) {
    const sec = el('section', 'dsec');
    sec.append(el('h4', 'dsec__title', title));
    return sec;
}

// Tags (sync)
function tagsSection(t) {
    const sec = sectionShell('Tags');
    const wrap = el('div', 'dsec__tags');
    const selected = new Set((t.tags || []).map((x) => x.id));
    state.tags.forEach((tag) => {
        const b = el('button', 'chip chip--tag' + (selected.has(tag.id) ? ' is-on' : ''), `#${tag.name}`);
        b.addEventListener('click', async () => {
            selected.has(tag.id) ? selected.delete(tag.id) : selected.add(tag.id);
            try {
                await api.post(`/tasks/${t.id}/tags`, { tag_ids: [...selected] });
                b.classList.toggle('is-on');
                reload();
            } catch (e) { apiError(e, 'Failed to sync tags.'); }
        });
        wrap.append(b);
    });
    if (!state.tags.length) wrap.append(el('span', 'tm-chips__empty', 'Create tags in the sidebar first.'));
    sec.append(wrap);
    return sec;
}

// Subtasks
function subtasksSection(t) {
    const done = (t.subtasks || []).filter((s) => s.is_completed).length;
    const sec = sectionShell(`Subtasks (${done}/${(t.subtasks || []).length})`);
    const ul = el('ul', 'dlist');
    (t.subtasks || []).forEach((s) => {
        const li = el('li', 'dlist__item');
        const cb = el('input');
        cb.type = 'checkbox';
        cb.checked = s.is_completed;
        cb.addEventListener('change', async () => {
            try { await api.patch(`/subtasks/${s.id}/toggle`); openDrawer(t.id); reload(); }
            catch (e) { apiError(e, 'Failed to toggle subtask.'); }
        });
        const label = el('span', s.is_completed ? 'is-struck' : null, s.title);
        const del = el('button', 'chip__x', '×');
        del.addEventListener('click', async () => {
            try { await api.delete(`/subtasks/${s.id}`); openDrawer(t.id); }
            catch (e) { apiError(e, 'Failed to delete subtask.'); }
        });
        li.append(cb, label, del);
        ul.append(li);
    });
    sec.append(ul);
    const form = el('form', 'dsec__form');
    const inp = el('input');
    inp.type = 'text';
    inp.placeholder = 'Add subtask…';
    inp.maxLength = 255;
    const btn = el('button', 'btn', 'Add');
    btn.type = 'submit';
    form.append(inp, btn);
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const title = inp.value.trim();
        if (!title) return;
        try { await api.post(`/tasks/${t.id}/subtasks`, { title }); openDrawer(t.id); }
        catch (err) { apiError(err, 'Failed to add subtask.'); }
    });
    sec.append(form);
    return sec;
}

// Comments
function commentsSection(t) {
    const sec = sectionShell(`Comments (${(t.comments || []).length})`);
    const ul = el('ul', 'dlist');
    (t.comments || []).forEach((c) => {
        const li = el('li', 'comment');
        const head = el('div', 'comment__head');
        head.append(el('span', 'comment__author', c.user?.name || 'User'));
        head.append(el('span', 'comment__time', c.created_at));
        li.append(head, el('div', 'comment__body', c.body));
        if (c.user?.id === meId) {
            const del = el('button', 'chip__x', '×');
            del.addEventListener('click', async () => {
                try { await api.delete(`/comments/${c.id}`); openDrawer(t.id); }
                catch (e) { apiError(e, 'Failed to delete comment.'); }
            });
            head.append(del);
        }
        ul.append(li);
    });
    sec.append(ul);
    const form = el('form', 'dsec__form');
    const inp = el('input');
    inp.type = 'text';
    inp.placeholder = 'Write a comment…';
    inp.maxLength = 2000;
    const btn = el('button', 'btn', 'Send');
    btn.type = 'submit';
    form.append(inp, btn);
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = inp.value.trim();
        if (!body) return;
        try { await api.post(`/tasks/${t.id}/comments`, { body }); openDrawer(t.id); }
        catch (err) { apiError(err, 'Failed to add comment.'); }
    });
    sec.append(form);
    return sec;
}

// Attachments
function attachmentsSection(t) {
    const sec = sectionShell(`Attachments (${(t.attachments || []).length})`);
    const ul = el('ul', 'dlist');
    (t.attachments || []).forEach((a) => {
        const li = el('li', 'dlist__item');
        const link = el('a', 'attach__link', a.original_name);
        link.href = a.url;
        link.target = '_blank';
        link.rel = 'noopener';
        const del = el('button', 'chip__x', '×');
        del.addEventListener('click', async () => {
            try { await api.delete(`/tasks/${t.id}/attachments/${a.id}`); openDrawer(t.id); }
            catch (e) { apiError(e, 'Failed to delete attachment.'); }
        });
        li.append(link, del);
        ul.append(li);
    });
    sec.append(ul);
    const form = el('form', 'dsec__form');
    const file = el('input');
    file.type = 'file';
    const btn = el('button', 'btn', 'Upload');
    btn.type = 'submit';
    form.append(file, btn);
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!file.files.length) return;
        const fd = new FormData();
        fd.append('file', file.files[0]);
        try {
            await api.post(`/tasks/${t.id}/attachments`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
            openDrawer(t.id);
        } catch (err) { apiError(err, 'Failed to upload file.'); }
    });
    sec.append(form);
    return sec;
}

// Time logs
function timeLogsSection(t) {
    const logs = t.time_logs || [];
    const running = logs.find((l) => l.is_active);
    const sec = sectionShell(`Time logs (${t.total_time_minutes || 0} min)`);

    const controls = el('div', 'dsec__form');
    if (running) {
        const stop = el('button', 'btn btn--danger', 'Stop timer');
        stop.addEventListener('click', async () => {
            try { await api.patch(`/time-logs/${running.id}/stop`); openDrawer(t.id); reload(); }
            catch (e) { apiError(e, 'Failed to stop timer.'); }
        });
        controls.append(el('span', 'timer-live', '● running'), stop);
    } else {
        const start = el('button', 'btn btn--primary', 'Start timer');
        start.addEventListener('click', async () => {
            try { await api.post(`/tasks/${t.id}/time-logs`, {}); openDrawer(t.id); reload(); }
            catch (e) { apiError(e, 'Failed to start timer.'); }
        });
        controls.append(start);
    }
    sec.append(controls);

    const ul = el('ul', 'dlist');
    logs.forEach((l) => {
        const li = el('li', 'dlist__item');
        const label = l.is_active
            ? `${l.started_at} — running`
            : `${l.started_at} · ${l.duration_minutes ?? 0} min`;
        li.append(el('span', null, label));
        const del = el('button', 'chip__x', '×');
        del.addEventListener('click', async () => {
            try { await api.delete(`/time-logs/${l.id}`); openDrawer(t.id); reload(); }
            catch (e) { apiError(e, 'Failed to delete time log.'); }
        });
        li.append(del);
        ul.append(li);
    });
    sec.append(ul);
    return sec;
}

// ── Boot ──────────────────────────────────────────────────────────────────
(async function boot() {
    await Promise.all([loadCategories(), loadTags()]);
    reload();
})();
