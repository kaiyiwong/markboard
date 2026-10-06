<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * An edit refused because the file changed since the user saw it (a 412). History, not index:
 * it is keyed by path and survives `markboard:sync --fresh`.
 */
#[Unguarded]
class Conflict extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'parameters' => 'array',
        ];
    }
}
