<?php

namespace Modules\Chat\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'body' => $this->body,
            'client_message_id' => $this->client_message_id,
            'sender' => new ChatUserResource($this->whenLoaded('sender')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
