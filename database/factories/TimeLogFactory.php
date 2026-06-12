<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeLog>
 */
class TimeLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'duration_minutes' => 60,
            'note' => fake()->optional()->sentence(),
        ];
    }

    /**
     * A running timer: started but not yet ended.
     */
    public function running(): static
    {
        return $this->state(fn () => [
            'started_at' => now()->subMinutes(30),
            'ended_at' => null,
            'duration_minutes' => null,
        ]);
    }
}
