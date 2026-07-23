<?php

namespace Modules\TaskManager\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\TaskManager\Database\Factories\SubtaskFactory;

class Subtask extends Model
{
    protected static function newFactory(): Factory
    {
        return SubtaskFactory::new();
    }

    use HasFactory;

    protected $fillable = ['task_id', 'title', 'is_completed', 'order'];

    protected $casts = ['is_completed' => 'boolean'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
