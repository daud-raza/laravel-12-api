<?php

namespace Modules\TaskManager\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\TaskManager\Models\Task;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(['pending', 'in_progress', 'completed']),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+30 days')?->format('Y-m-d'),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending', 'completed_at' => null]);
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }

    /**
     * A recurring task with a concrete due date so the observer can roll it forward.
     */
    public function recurring(string $type = 'daily'): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'is_recurring' => true,
            'recurrence_type' => $type,
            'due_date' => now()->addDay()->format('Y-m-d'),
        ]);
    }
}
