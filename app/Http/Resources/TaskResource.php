<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'is_pinned' => $this->is_pinned,
            'is_recurring' => $this->is_recurring,
            'recurrence_type' => $this->recurrence_type,
            'recurrence_ends_at' => $this->recurrence_ends_at?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at?->toDateTimeString(),
            'is_overdue' => $this->due_date && $this->due_date->isBefore(today()) && $this->status !== 'completed',
            'category' => new CategoryResource($this->whenLoaded('category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'subtasks' => SubtaskResource::collection($this->whenLoaded('subtasks')),
            'time_logs' => TimeLogResource::collection($this->whenLoaded('timeLogs')),
            'total_time_minutes' => $this->whenLoaded('timeLogs', fn () => $this->timeLogs->sum('duration_minutes')),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
        ];
    }
}
