<?php

namespace App\Models;

use App\Sync\SourceKind;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A file sync has read: its hash (the page's etag), the mtime and size that let sync skip it,
 * and its format errors. Hub files (registry, priorities) have no project.
 */
#[Table(timestamps: false)]
#[Unguarded]
class SourceFile extends Model
{
    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<PipelineRow, $this> */
    public function pipelineRows(): HasMany
    {
        return $this->hasMany(PipelineRow::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => SourceKind::class,
            'mtime' => 'integer',
            'size' => 'integer',
            'editable' => 'boolean',
            'errors' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
