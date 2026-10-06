<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ApplyConflict;
use App\Actions\DiscardConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\FileEditRequest;
use App\Models\Conflict;
use Illuminate\Http\JsonResponse;

/**
 * The conflict panel's two buttons. Conflicts exist only in the database, so they are the one
 * thing the API addresses by database id [D30].
 */
class ConflictController extends Controller
{
    /** If-Match is the etag the panel showed, the version the edit is applied to. */
    public function apply(FileEditRequest $request, Conflict $conflict, ApplyConflict $apply): JsonResponse
    {
        return $apply->handle($conflict, $request->etag());
    }

    /** Needs no If-Match: it changes no file. */
    public function discard(Conflict $conflict, DiscardConflict $discard): JsonResponse
    {
        $discard->handle($conflict);

        return response()->json(['data' => ['id' => $conflict->id, 'status' => $conflict->status]]);
    }
}
