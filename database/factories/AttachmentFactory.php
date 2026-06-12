<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->word().'.pdf';

        return [
            'task_id' => Task::factory(),
            'original_name' => $name,
            'path' => 'attachments/task-1/'.fake()->uuid().'.pdf',
            'size' => fake()->numberBetween(1000, 5_000_000),
            'mime_type' => 'application/pdf',
        ];
    }
}
