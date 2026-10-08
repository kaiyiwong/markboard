<?php

namespace App\Http\Resources;

use App\Models\SourceFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A file a page shows: its hash is the etag an edit sends back as If-Match, and a file that isn't
 * editable is shown read-only with its errors.
 *
 * @mixin SourceFile
 */
class SourceFileResource extends JsonResource
{
    /**
     * @return array{path: string, etag: string|null, editable: bool, errors: list<array{line: int, message: string}>, sync_error: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'path' => $this->path,
            'etag' => $this->hash,
            'editable' => $this->editable,
            'errors' => $this->errors ?? [],
            'sync_error' => $this->sync_error,
        ];
    }
}
