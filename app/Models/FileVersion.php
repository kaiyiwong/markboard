<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * A file's content as sync once saw it, kept so a conflict can show what changed since.
 * History, not index: it is keyed by path and survives `markboard:sync --fresh`.
 */
#[Table(timestamps: false)]
#[Unguarded]
class FileVersion extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}
