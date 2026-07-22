<?php

namespace Modules\TaskManager\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\TaskManager\Models\Attachment;
use Modules\TaskManager\Models\Task;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

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
