<?php

namespace App\Models;

use App\Markdown\Stage;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pipeline row as sync last read it, addressed by its position among the table's rows.
 */
#[Table(timestamps: false)]
#[Unguarded]
class PipelineRow extends Model
{
    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'stage' => Stage::class,
            'date' => 'date',
        ];
    }
}
