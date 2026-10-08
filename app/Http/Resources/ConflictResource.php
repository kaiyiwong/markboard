<?php

namespace App\Http\Resources;

use App\Markdown\LineDiff;
use App\Models\Conflict;
use App\Models\SourceFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An open conflict as the conflict panel shows it, in a 412 body and on the pages: the refused
 * edit, the file's current etag (what Apply must send as If-Match), whether the edit can still be
 * applied to the current version, the task or row as it is now, and a line diff from the version
 * the user edited to the current one (null when the base version is no longer stored).
 *
 * @mixin Conflict
 */
class ConflictResource extends JsonResource
{
    /**
     * @return array{id: int, operation: string, summary: string, etag: string|null, applicable: bool, current: array<string, mixed>|null, diff: list<array{op: string, old: int|null, new: int|null, text: string}>|null}
     */
    public function toArray(Request $request): array
    {
        $file = SourceFile::firstWhere('path_hash', $this->path_hash);
        $edit = $this->resource->edit();
        $base = $this->resource->base();
        $current = $this->resource->versionContent($file?->hash);

        return [
            'id' => $this->id,
            'operation' => $this->operation->value,
            'summary' => $edit->summary(),
            'etag' => $file?->hash,
            'applicable' => $current !== null && $this->resource->canApplyTo($current),
            'current' => $file === null ? null : $edit->current($file)?->resolve(),
            'diff' => $base === null || $current === null ? null : LineDiff::between($base, $current),
        ];
    }
}
