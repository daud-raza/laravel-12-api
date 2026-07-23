<?php

namespace Modules\Chat\Policies;

use App\Models\User;
use Modules\Chat\Models\Conversation;

class ConversationPolicy
{
    private function isParticipant(User $user, Conversation $conversation): bool
    {
        return $conversation->participants()->whereKey($user->id)->exists();
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }
}
