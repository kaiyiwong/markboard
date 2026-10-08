<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConflictResource;
use App\Http\Resources\PipelineRowResource;
use App\Http\Resources\SourceFileResource;
use App\Markdown\Stage;
use App\Models\Conflict;
use App\Models\SourceFile;
use App\Sync\SourceKind;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    /**
     * One board per registered project that has a pipeline.md, in the Projects page's order, each
     * with the open conflicts on its file.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Pipeline/Index', DB::transaction(fn (): array => [
            'boards' => SourceFile::where('kind', SourceKind::Pipeline)
                ->join('projects', 'projects.id', '=', 'source_files.project_id')
                ->orderBy('projects.category_rank')->orderBy('projects.registry_order')
                ->select('source_files.*')
                ->with(['project', 'pipelineRows' => fn ($rows) => $rows->orderBy('position')])
                ->get()
                ->map(fn (SourceFile $file): array => [
                    'project' => ['id' => $file->project?->id, 'name' => $file->project?->name],
                    'file' => SourceFileResource::make($file)->resolve(),
                    'rows' => PipelineRowResource::collection($file->pipelineRows)->resolve(),
                    'conflicts' => ConflictResource::collection(Conflict::openOn($file))->resolve(),
                ]),
            'stages' => [
                'open' => array_values(array_map(fn (Stage $stage): string => $stage->value, array_filter(Stage::cases(), fn (Stage $stage): bool => $stage->isOpen()))),
                'closed' => array_values(array_map(fn (Stage $stage): string => $stage->value, array_filter(Stage::cases(), fn (Stage $stage): bool => ! $stage->isOpen()))),
            ],
        ]));
    }
}
