<?php

namespace Modules\Chat\Database\Factories;

use Modules\Chat\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'type' => 'direct',
            'created_by' => User::factory(),
            'direct_hash' => null,
            'last_message_at' => null,
        ];
    }

    public function group(): static
    {
        return $this->state(fn () => ['type' => 'group', 'direct_hash' => null]);
    }
}
