import './bootstrap';

/**
 * Chat client (hybrid): the conversation list is server-rendered by Blade.
 * This script drives live interaction — load/send messages via the JSON API,
 * receive new messages via Echo (Reverb) with a polling fallback.
 */

const token = document.querySelector('meta[name="api-token"]')?.content;
const meId = parseInt(document.querySelector('meta[name="me-id"]')?.content, 10);

if (token) {
    window.axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    window.axios.defaults.headers.common['Accept'] = 'application/json';
}

const messagesEl = document.getElementById('messages');
const conversationId = messagesEl?.dataset.conversation ? parseInt(messagesEl.dataset.conversation, 10) : null;

// ── Rendering ────────────────────────────────────────────────────────────
function renderMessage(m, { prepend = false } = {}) {
    if (document.querySelector(`[data-mid="${m.id}"]`)) return; // dedup
    const row = document.createElement('div');
    row.className = 'msg ' + (m.user_id === meId ? 'msg--mine' : 'msg--theirs');
    row.dataset.mid = m.id;
    const bubble = document.createElement('div');
    bubble.className = 'msg__bubble';
    bubble.textContent = m.body; // textContent → XSS-safe
    row.appendChild(bubble);
    if (prepend) messagesEl.prepend(row);
    else messagesEl.appendChild(row);
}

function scrollToBottom() {
    if (messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight;
}

// ── Load messages ─────────────────────────────────────────────────────────
let oldestCursor = null;
let latestId = 0;

async function loadInitial() {
    if (!conversationId) return;
    messagesEl.innerHTML = '';
    const { data } = await window.axios.get(`/api/conversations/${conversationId}/messages?limit=30`);
    // API returns newest-first; render ascending.
    const list = [...data.data].reverse();
    list.forEach((m) => {
        renderMessage(m);
        latestId = Math.max(latestId, m.id);
    });
    oldestCursor = data.meta.next_cursor;
    scrollToBottom();
    markRead();
}

async function markRead() {
    if (!conversationId) return;
    try {
        await window.axios.post(`/api/conversations/${conversationId}/read`, { last_read_message_id: latestId });
        const badge = document.querySelector(`.conv.is-active .conv__badge`);
        if (badge) badge.remove();
    } catch (e) { /* non-critical */ }
}

// ── Send ────────────────────────────────────────────────────────────────
const composer = document.getElementById('composer');
const input = document.getElementById('composer-input');
const sendBtn = document.getElementById('composer-send');

input?.addEventListener('input', () => {
    sendBtn.disabled = input.value.trim().length === 0;
});

composer?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = input.value.trim();
    if (!body) return;

    const clientId = (crypto.randomUUID && crypto.randomUUID()) || `${Date.now()}-${Math.random()}`;
    input.value = '';
    sendBtn.disabled = true;

    try {
        const { data } = await window.axios.post(`/api/conversations/${conversationId}/messages`, {
            body,
            client_message_id: clientId,
        });
        renderMessage(data);
        latestId = Math.max(latestId, data.id);
        scrollToBottom();
    } catch (err) {
        // Restore text so the user can retry (idempotent via a new client id).
        input.value = body;
        sendBtn.disabled = false;
        alert('Message failed to send. Try again.');
    }
});

// ── Real-time (Echo/Reverb) + polling fallback ─────────────────────────────
let usingRealtime = false;

function startRealtime() {
    if (!conversationId || !window.Echo) return;
    try {
        window.Echo.private(`conversation.${conversationId}`)
            .listen('.message.sent', (m) => {
                renderMessage(m);
                latestId = Math.max(latestId, m.id);
                scrollToBottom();
                if (m.user_id !== meId) markRead();
            });
        usingRealtime = true;
    } catch (e) {
        usingRealtime = false;
    }
}

function startPolling() {
    if (!conversationId) return;
    setInterval(async () => {
        if (usingRealtime) return; // realtime active → skip poll
        try {
            const { data } = await window.axios.get(`/api/conversations/${conversationId}/messages?limit=30`);
            [...data.data].reverse().forEach((m) => {
                if (m.id > latestId) {
                    renderMessage(m);
                    latestId = Math.max(latestId, m.id);
                    scrollToBottom();
                }
            });
        } catch (e) { /* ignore transient */ }
    }, 4000);
}

// ── User search (start a conversation) ──────────────────────────────────────
const searchInput = document.getElementById('user-search');
const searchResults = document.getElementById('user-search-results');
const searchPanel = document.getElementById('chat-search');
const newChatBtn = document.getElementById('new-chat-btn');
let searchTimer = null;

// "+ New chat" reveals the user-search panel and focuses it.
newChatBtn?.addEventListener('click', () => {
    searchPanel?.classList.toggle('is-hidden');
    if (!searchPanel?.classList.contains('is-hidden')) {
        searchInput?.focus();
    }
});

searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const term = searchInput.value.trim();
    if (term.length < 2) { searchResults.innerHTML = ''; return; }
    searchTimer = setTimeout(async () => {
        const { data } = await window.axios.get(`/api/users?search=${encodeURIComponent(term)}`);
        searchResults.innerHTML = '';
        data.data.forEach((u) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'search-hit';
            btn.textContent = u.name;
            btn.addEventListener('click', () => startConversation(u.id));
            searchResults.appendChild(btn);
        });
    }, 250);
});

async function startConversation(userId) {
    const { data } = await window.axios.post('/api/conversations', { user_id: userId });
    window.location.href = `/chat/${data.id}`;
}

// ── Boot ────────────────────────────────────────────────────────────────
if (conversationId) {
    loadInitial().then(() => {
        startRealtime();
        startPolling();
    });
}
