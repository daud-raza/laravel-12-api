<?php

namespace Modules\TaskManager\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\TaskManager\Database\Factories\AttachmentFactory;

class Attachment extends Model
{
    protected static function newFactory(): Factory
    {
        return AttachmentFactory::new();
    }

    use HasFactory;

    protected $fillable = ['task_id', 'original_name', 'path', 'size', 'mime_type'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
