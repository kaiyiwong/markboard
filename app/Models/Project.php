<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registered project, copied from a row of projects.md. Its key is the registry id.
 */
#[Table(keyType: 'string', incrementing: false, timestamps: false)]
#[Unguarded]
class Project extends Model
{
    /** @return HasMany<SourceFile, $this> */
    public function sourceFiles(): HasMany
    {
        return $this->hasMany(SourceFile::class);
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
            'registry_order' => 'integer',
            'category_rank' => 'integer',
            'folder_found' => 'boolean',
        ];
    }
}
