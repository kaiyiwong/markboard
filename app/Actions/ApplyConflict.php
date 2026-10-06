<?php

namespace App\Actions;

use App\Models\Conflict;
use App\Models\SourceFile;
use Illuminate\Http\JsonResponse;

/**
 * Runs a refused edit again, on the version on disk now, through the write path. It needs the etag
 * the conflict panel showed: if the file changed again since, this conflict is superseded by a new one.
 */
final readonly class ApplyConflict
{
    public function __construct(
        private WriteFile $write,
    ) {}

    public function handle(Conflict $conflict, string $etag): JsonResponse
    {
        abort_unless($conflict->status === Conflict::OPEN, 409, "This conflict is {$conflict->status} already.");
        $project = SourceFile::firstWhere('path_hash', $conflict->path_hash)?->project;
        abort_if($project === null, 409, 'The file this conflict is about is no longer in the hub.');

        return $this->write->write($project, $conflict->edit(), $etag, $conflict);
    }
}
