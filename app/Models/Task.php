<?php

namespace App\Models;

use App\Markdown\Section;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A task as sync last read it. The date and string columns are copied out of the metadata so
 * they can be filtered; the metadata itself keeps every pair as written.
 */
#[Table(timestamps: false)]
#[Unguarded]
class Task extends Model
{
    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<SourceFile, $this> */
    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(SourceFile::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'section' => Section::class,
            'position' => 'integer',
            'checked' => 'boolean',
            'metadata' => 'array',
            'due' => 'date',
            'started' => 'date',
            'since' => 'date',
            'done' => 'date',
            'cancelled' => 'date',
            'from_section' => Section::class,
            'notes' => 'array',
            'line_start' => 'integer',
            'line_end' => 'integer',
        ];
    }
}
