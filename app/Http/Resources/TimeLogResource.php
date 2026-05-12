<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'started_at'       => $this->started_at->toDateTimeString(),
            'ended_at'         => $this->ended_at?->toDateTimeString(),
            'duration_minutes' => $this->duration_minutes,
            'note'             => $this->note,
            'is_active'        => is_null($this->ended_at),
            'created_at'       => $this->created_at->toDateTimeString(),
        ];
    }
}
