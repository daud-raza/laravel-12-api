<?php

namespace Modules\Chat\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Chat\Http\Resources\ChatUserResource;
use Modules\Chat\Services\ChatService;

class ChatUserController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'search' => ['required', 'string', 'min:2', 'max:255'],
            ]);

            $users = $this->chat->searchUsers($request->user(), $request->query('search'));

            return response()->json([
                'message' => 'Users fetched successfully',
                'data' => ChatUserResource::collection($users),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'total' => $users->total(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to search users', ['error' => $e]);

            return response()->json(['message' => 'Something went wrong while searching users.'], 500);
        }
    }
}
