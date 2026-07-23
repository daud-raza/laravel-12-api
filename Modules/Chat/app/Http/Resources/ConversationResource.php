<?php

namespace Modules\Chat\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            // 'other_participant' and 'unread_count' are injected by the service
            // as attributes on the model to keep this resource query-free.
            'other_participant' => $this->when(
                $this->relationLoaded('participants') || isset($this->other_participant),
                fn () => $this->other_participant
                    ? new ChatUserResource($this->other_participant)
                    : null
            ),
            'last_message' => $this->when(
                $this->relationLoaded('lastMessage'),
                fn () => $this->lastMessage ? new MessageResource($this->lastMessage) : null
            ),
            'unread_count' => $this->when(isset($this->unread_count), fn () => (int) $this->unread_count),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
        ];
    }
}
