<?php

namespace Modules\Chat\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Chat\Http\Requests\StoreMessageRequest;
use Modules\Chat\Http\Resources\MessageResource;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Services\ChatService;

class MessageController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        try {
            $this->authorize('view', $conversation);

            $limit = (int) $request->query('limit', 30);
            $messages = $this->chat->messages($conversation, $limit);

            return response()->json([
                'message' => 'Messages fetched successfully',
                'data' => MessageResource::collection($messages),
                'meta' => [
                    'next_cursor' => $messages->nextCursor()?->encode(),
                    'has_more' => $messages->hasMorePages(),
                ],
            ]);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch messages', ['conversation_id' => $conversation->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while fetching messages.'], 500);
        }
    }

    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        try {
            $this->authorize('sendMessage', $conversation);

            $data = $request->validated();

            $message = $this->chat->sendMessage(
                $conversation,
                $request->user(),
                $data['body'],
                $data['client_message_id'],
            );

            return response()->json(
                new MessageResource($message),
                $message->wasRecentlyCreated ? 201 : 200
            );
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to send messages here.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to send message', ['conversation_id' => $conversation->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while sending the message.'], 500);
        }
    }
}
