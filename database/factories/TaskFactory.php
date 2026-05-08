<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'     => \App\Models\User::factory(),
            'title'       => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status'      => fake()->randomElement(['pending', 'in_progress', 'completed']),
            'priority'    => fake()->randomElement(['low', 'medium', 'high']),
            'due_date'    => fake()->optional()->dateTimeBetween('now', '+30 days')?->format('Y-m-d'),
        ];
    }
}
