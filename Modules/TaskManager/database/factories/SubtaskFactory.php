<?php

namespace Modules\TaskManager\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\TaskManager\Models\Subtask;
use Modules\TaskManager\Models\Task;

/**
 * @extends Factory<Subtask>
 */
class SubtaskFactory extends Factory
{
    protected $model = Subtask::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'title' => fake()->sentence(3),
            'is_completed' => false,
            'order' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['is_completed' => true]);
    }
}
