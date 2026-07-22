<?php

namespace Modules\Chat\Policies;

use App\Models\User;
use Modules\Chat\Models\Message;

class MessagePolicy
{
    // Reserved for the future "delete own message" endpoint. Sender only.
    public function delete(User $user, Message $message): bool
    {
        return $user->id === $message->user_id;
    }
}
