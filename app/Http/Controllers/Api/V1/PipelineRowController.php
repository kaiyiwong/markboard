<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AddPipelineRow;
use App\Actions\EditPipelineRow;
use App\Actions\WriteFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddPipelineRowRequest;
use App\Http\Requests\EditPipelineRowRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * Rows of the pipeline.md in a project's folder. Rows have no IDs: a row is its 1-based position,
 * which If-Match makes safe, since it proves the file is the one the user saw [D9].
 */
class PipelineRowController extends Controller
{
    public function __construct(
        private readonly WriteFile $write,
    ) {}

    public function store(AddPipelineRowRequest $request, Project $project): JsonResponse
    {
        return $this->write->write($project, new AddPipelineRow([...$request->validated(), 'date' => $request->validated('date')]), $request->etag());
    }

    public function update(EditPipelineRowRequest $request, Project $project, int $row): JsonResponse
    {
        return $this->write->write($project, new EditPipelineRow($row, $request->validated()), $request->etag());
    }
}
