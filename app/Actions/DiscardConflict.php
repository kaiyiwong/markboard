<?php

namespace App\Actions;

use App\Models\Conflict;

/** Closes a conflict without changing the file. */
final readonly class DiscardConflict
{
    public function handle(Conflict $conflict): void
    {
        abort_unless($conflict->status === Conflict::OPEN, 409, "This conflict is {$conflict->status} already.");
        $conflict->update(['status' => Conflict::RESOLVED]);
    }
}
