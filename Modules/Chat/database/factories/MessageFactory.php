<?php

namespace Modules\Chat\Database\Factories;

use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'client_message_id' => (string) Str::uuid(),
        ];
    }
}
