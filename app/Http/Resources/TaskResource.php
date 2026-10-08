<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A task as the file holds it: the metadata pairs as written, plus the values the index copied
 * out of them. Tasks are addressed by their ID as written (T09), never a database id.
 *
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'task_id' => $this->task_id,
            'number' => $this->number,
            'section' => $this->section,
            'position' => $this->position,
            'checked' => $this->checked,
            'title' => $this->title,
            'metadata' => $this->metadata,
            'due' => $this->due?->toDateString(),
            'overdue' => $this->section->isOpen() && $this->due !== null
                && $this->due->toDateString() < today(config()->string('markboard.timezone'))->toDateString(),
            'from_section' => $this->from_section,
            'proof' => $this->proof,
            'notes' => $this->notes,
            'line_start' => $this->line_start,
            'line_end' => $this->line_end,
        ];
    }
}
