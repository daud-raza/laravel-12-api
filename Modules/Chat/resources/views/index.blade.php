@extends('layouts.app')

@section('title', 'Chat')

@push('head')
    <meta name="api-token" content="{{ $apiToken }}">
    <meta name="me-id" content="{{ $me->id }}">
@endpush

@section('content')
<div class="chat" data-active="{{ $active?->id }}">
    {{-- Left: conversation list --}}
    <aside class="chat-list">
        <header class="chat-list__head">
            <strong>Chats</strong>
            <div class="chat-list__actions">
                <button id="new-chat-btn" class="newchat" type="button">+ New chat</button>
                <form method="POST" action="/logout">@csrf<button class="linkbtn">Log out</button></form>
            </div>
        </header>

        <div id="chat-search" class="chat-search is-hidden">
            <input id="user-search" type="text" placeholder="Search users by name…" autocomplete="off">
            <div id="user-search-results" class="chat-search__results"></div>
        </div>

        <ul class="conv-list">
            @forelse ($conversations as $conversation)
                <li>
                    <a href="/chat/{{ $conversation->id }}"
                       class="conv {{ $active?->id === $conversation->id ? 'is-active' : '' }}">
                        <span class="conv__name">
                            {{ $conversation->other_participant?->name ?? 'Unknown' }}
                        </span>
                        <span class="conv__preview">
                            {{ \Illuminate\Support\Str::limit($conversation->lastMessage?->body, 40) }}
                        </span>
                        @if ($conversation->unread_count)
                            <span class="conv__badge">{{ $conversation->unread_count }}</span>
                        @endif
                    </a>
                </li>
            @empty
                <li class="conv-empty">No conversations yet. Search a user above to start one.</li>
            @endforelse
        </ul>
    </aside>

    {{-- Right: active conversation --}}
    <main class="chat-window">
        @if ($active)
            <header class="chat-window__head">
                {{ $active->other_participant?->name ?? 'Conversation' }}
            </header>
            <div id="messages" class="messages" data-conversation="{{ $active->id }}">
                <div class="messages__loading">Loading…</div>
            </div>
            <form id="composer" class="composer" autocomplete="off">
                <textarea id="composer-input" rows="1" placeholder="Type a message…"></textarea>
                <button type="submit" id="composer-send" disabled>Send</button>
            </form>
        @else
            <div class="chat-window__empty">
                <p>Select a conversation, or search a user to start chatting.</p>
            </div>
        @endif
    </main>
</div>
@endsection

@push('scripts')
    @vite('resources/js/chat.js')
@endpush
