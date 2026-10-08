<?php

namespace App\Http\Resources;

use App\Markdown\Section;
use App\Models\Project;
use App\Models\SourceFile;
use App\Sync\SourceKind;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A registered project. On the Projects page it also carries its task counts, its due badge and
 * the state of its TASKS.md, which need the counts and files loaded (see ProjectController::index).
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /** Due within this many days, today included, earns the due-soon badge. */
    private const int DUE_SOON_DAYS = 7;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $project = [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'status' => $this->status,
            'next_milestone' => $this->next_milestone,
            'docs' => $this->docs,
            'folder_found' => $this->folder_found,
        ];

        return $this->relationLoaded('sourceFiles') ? [...$project, ...$this->summary()] : $project;
    }

    /**
     * @return array{tasks_file: 'missing'|'errors'|'ok', error_count: int, counts: array<string, int>, due: 'overdue'|'soon'|null, sync_error: string|null}
     */
    private function summary(): array
    {
        $tasks = $this->sourceFiles->firstWhere('kind', SourceKind::Tasks);
        $nextDue = $this->resource->getAttribute('next_due');
        $today = today(config()->string('markboard.timezone'));

        return [
            'tasks_file' => match (true) {
                $tasks === null => 'missing',
                $tasks->editable => 'ok',
                default => 'errors',
            },
            'error_count' => count($tasks->errors ?? []),
            'counts' => [
                Section::UpNext->value => (int) $this->resource->getAttribute('up_next'),
                Section::InProgress->value => (int) $this->resource->getAttribute('in_progress'),
                Section::WaitingOn->value => (int) $this->resource->getAttribute('waiting_on'),
                Section::Done->value => (int) $this->resource->getAttribute('done'),
            ],
            'due' => match (true) {
                ! is_string($nextDue) => null,
                $nextDue < $today->toDateString() => 'overdue',
                $nextDue <= $today->addDays(self::DUE_SOON_DAYS)->toDateString() => 'soon',
                default => null,
            },
            'sync_error' => $this->sourceFiles->map(fn (SourceFile $file): ?string => $file->sync_error)->filter()->first(),
        ];
    }
}
