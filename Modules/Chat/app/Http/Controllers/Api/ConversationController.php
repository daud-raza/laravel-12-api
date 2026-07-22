<?php

namespace Modules\Chat\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Chat\Http\Requests\MarkReadRequest;
use Modules\Chat\Http\Requests\StoreConversationRequest;
use Modules\Chat\Http\Resources\ConversationResource;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Services\ChatService;

class ConversationController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $conversations = $this->chat->listConversations(
                $request->user(),
                $request->query('search'),
            );

            return response()->json([
                'message' => 'Conversations fetched successfully',
                'data' => ConversationResource::collection($conversations),
                'meta' => [
                    'current_page' => $conversations->currentPage(),
                    'last_page' => $conversations->lastPage(),
                    'total' => $conversations->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch conversations', ['error' => $e]);

            return response()->json(['message' => 'Something went wrong while fetching conversations.'], 500);
        }
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        try {
            $conversation = $this->chat->findOrCreateDirect(
                $request->user(),
                (int) $request->validated()['user_id'],
            );

            $created = $conversation->wasRecentlyCreated;
            $conversation->load(['lastMessage', 'participants']);
            $this->chat->decorate($conversation, $request->user());

            return response()->json(
                new ConversationResource($conversation),
                $created ? 201 : 200
            );
        } catch (\Throwable $e) {
            Log::error('Failed to create conversation', ['error' => $e]);

            return response()->json(['message' => 'Something went wrong while creating the conversation.'], 500);
        }
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        try {
            $this->authorize('view', $conversation);
            $conversation->load(['lastMessage', 'participants']);
            $this->chat->decorate($conversation, $request->user());

            return response()->json(new ConversationResource($conversation));
        } catch (AuthorizationException) {
            // Hide existence from non-participants.
            return response()->json(['message' => 'Conversation not found.'], 404);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch conversation', ['conversation_id' => $conversation->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while fetching the conversation.'], 500);
        }
    }

    public function read(MarkReadRequest $request, Conversation $conversation): JsonResponse
    {
        try {
            $this->authorize('view', $conversation);

            $unread = $this->chat->markRead(
                $conversation,
                $request->user(),
                $request->validated()['last_read_message_id'] ?? null,
            );

            return response()->json(['message' => 'Marked as read', 'unread_count' => $unread]);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        } catch (\Throwable $e) {
            Log::error('Failed to mark conversation read', ['conversation_id' => $conversation->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while marking as read.'], 500);
        }
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        try {
            $this->authorize('delete', $conversation);

            DB::transaction(fn () => $conversation->delete());

            return response()->json(['message' => 'Conversation deleted successfully']);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to delete this conversation.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to delete conversation', ['conversation_id' => $conversation->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while deleting the conversation.'], 500);
        }
    }
}
