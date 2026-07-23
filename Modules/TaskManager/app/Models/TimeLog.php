<?php

namespace Modules\TaskManager\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\TaskManager\Database\Factories\TimeLogFactory;

class TimeLog extends Model
{
    protected static function newFactory(): Factory
    {
        return TimeLogFactory::new();
    }

    use HasFactory;

    protected $fillable = ['task_id', 'user_id', 'started_at', 'ended_at', 'duration_minutes', 'note'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
