<?php

namespace Modules\Chat\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Services\ChatService;

class ChatPageController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(Request $request): View
    {
        return view('chat::index', [
            'conversations' => $this->chat->listConversations($request->user()),
            'active' => null,
            'apiToken' => $this->issueToken($request),
            'me' => $request->user(),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        abort_unless(
            $conversation->participants()->whereKey($request->user()->id)->exists(),
            404
        );

        $this->chat->decorate($conversation->load(['participants', 'lastMessage']), $request->user());

        return view('chat::index', [
            'conversations' => $this->chat->listConversations($request->user()),
            'active' => $conversation,
            'apiToken' => $this->issueToken($request),
            'me' => $request->user(),
        ]);
    }

    /**
     * Mint a fresh Sanctum token for the browser's axios calls to the JSON API.
     * Old web tokens are pruned so they don't accumulate per page load.
     */
    private function issueToken(Request $request): string
    {
        $request->user()->tokens()->where('name', 'chat-web')->delete();

        return $request->user()->createToken('chat-web')->plainTextToken;
    }
}
