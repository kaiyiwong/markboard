<?php

namespace App\Sync;

use App\Markdown\FormatError;
use App\Markdown\PipelineFile;
use App\Markdown\PipelineRow as ParsedRow;
use App\Markdown\Priorities;
use App\Markdown\Registry;
use App\Markdown\Section;
use App\Markdown\Stage;
use App\Markdown\TasksFile;
use App\Models\Conflict;
use App\Models\FileVersion;
use App\Models\PipelineRow;
use App\Models\Project;
use App\Models\SourceFile;
use App\Models\Task;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Copies the hub's files into the index tables. The files are the source of truth: a changed file's
 * rows are deleted and inserted again from a fresh parse, never merged.
 *
 * A file is skipped while its mtime and size match the last sync and that sync happened more than
 * 2 seconds after the mtime (mtime has one-second resolution, so two same-size writes in one second
 * look alike). Otherwise it is hashed, and parsed only if the hash changed.
 */
final class HubSync
{
    /** Longer than the checker's 10-second timeout plus the write, so a write never outlives its lock. */
    private const int LOCK_SECONDS = 30;

    /** How many versions of each file are kept, besides those an open conflict refers to. */
    private const int VERSIONS_KEPT = 10;

    public function __construct(
        private readonly Hub $hub,
    ) {}

    /**
     * Syncs every source file and returns how many were re-indexed or removed.
     *
     * @param  bool  $fresh  empty the index tables first; file_versions and conflicts are history and are kept
     */
    public function run(bool $fresh = false): int
    {
        if ($fresh) {
            DB::transaction(function (): void {
                SourceFile::query()->delete();
                Project::query()->delete();
            });
        }
        if (! $this->hub->exists()) {
            return 0;
        }

        $count = 0;
        foreach ([[SourceKind::Registry, $this->hub->registryPath()], [SourceKind::Priorities, $this->hub->prioritiesPath()]] as [$kind, $path]) {
            $count += (int) $this->sync($kind, $path, null, SourceFile::firstWhere('path_hash', hash('sha256', $path)));
        }

        // Read after the registry, which deletes the files of projects it removed or moved.
        $files = SourceFile::whereNotNull('project_id')->get()->keyBy('path_hash');
        foreach (Project::all() as $project) {
            $found = is_dir($project->path);
            if ($found !== $project->folder_found) {
                $project->update(['folder_found' => $found]);
            }
            if (! $found) {
                $count += $project->sourceFiles()->delete();

                continue;
            }
            $this->removeStaleTempFiles($project->path);
            foreach ([[SourceKind::Tasks, 'TASKS.md'], [SourceKind::Pipeline, 'pipeline.md']] as [$kind, $name]) {
                $path = $project->path.'/'.$name;
                $count += (int) $this->sync($kind, $path, $project->id, $files->get(hash('sha256', $path)));
            }
        }

        return $count;
    }

    /** The lock a write and a sync of this file share, named after its real path. */
    public function lock(string $path): Lock
    {
        return Cache::lock('markboard:file:'.hash('sha256', realpath($path) ?: $path), self::LOCK_SECONDS);
    }

    /**
     * Sync's per-file step, for a caller that already holds the file's lock (the write path).
     * Returns whether the file was re-indexed. A failure is recorded on the file and rolls back,
     * so its old rows and old hash stay together and the next sync tries again.
     */
    public function syncLocked(SourceKind $kind, string $path, ?string $projectId): bool
    {
        clearstatcache(true, $path);
        $mtime = @filemtime($path);
        $bytes = @file_get_contents($path);
        if ($mtime === false || $bytes === false) {
            return false;
        }

        $pathHash = hash('sha256', $path);
        $hash = hash('sha256', $bytes);
        $stat = ['mtime' => $mtime, 'size' => strlen($bytes), 'synced_at' => now()];
        $record = SourceFile::firstWhere('path_hash', $pathHash);
        if ($record?->hash === $hash) {
            $record->update($stat);

            return false;
        }

        $identity = ['project_id' => $projectId, 'kind' => $kind, 'path' => $path];
        try {
            DB::transaction(function () use ($identity, $stat, $kind, $pathHash, $hash, $bytes): void {
                $file = SourceFile::updateOrCreate(['path_hash' => $pathHash], [...$identity, ...$stat, 'hash' => $hash, 'sync_error' => null]);
                [$editable, $errors] = match ($kind) {
                    SourceKind::Registry => $this->indexRegistry($bytes),
                    SourceKind::Priorities => $this->indexPriorities($bytes),
                    SourceKind::Tasks => $this->indexTasks($file, $bytes),
                    SourceKind::Pipeline => $this->indexPipeline($file, $bytes),
                };
                usort($errors, fn (FormatError $a, FormatError $b): int => $a->line <=> $b->line);
                $file->update([
                    'editable' => $editable,
                    'errors' => array_map(fn (FormatError $error): array => ['line' => $error->line, 'message' => $error->message], $errors),
                ]);
                $this->keepVersion($identity['path'], $pathHash, $hash, $bytes);
            });
        } catch (Throwable $e) {
            report($e);
            SourceFile::updateOrCreate(['path_hash' => $pathHash], [...$identity, 'sync_error' => $e->getMessage()]);

            return false;
        }

        return true;
    }

    private function sync(SourceKind $kind, string $path, ?string $projectId, ?SourceFile $record): bool
    {
        clearstatcache(true, $path);
        if (! is_file($path)) {
            return $record !== null && $this->remove($record);
        }

        if ($record !== null && $record->mtime === filemtime($path) && $record->size === filesize($path)
            && $record->synced_at !== null && $record->synced_at->getTimestamp() - $record->mtime > 2) {
            return false;
        }

        // A busy lock means a write holds it, and the write re-syncs the file itself.
        $lock = $this->lock($path);
        if (! $lock->get()) {
            return false;
        }
        try {
            return $this->syncLocked($kind, $path, $projectId);
        } finally {
            $lock->release();
        }
    }

    /** A file that is gone takes its rows with it; with no priorities.md every category ranks equal. */
    private function remove(SourceFile $record): bool
    {
        DB::transaction(function () use ($record): void {
            $record->delete();
            if ($record->kind === SourceKind::Priorities) {
                $this->rank(Priorities::parse(''));
            }
        });

        return true;
    }

    /**
     * Projects are upserted, not re-inserted, because their tasks hang off them: deleting one deletes
     * its files' rows too. That is what happens to a project the registry no longer lists, and to the
     * files of a project whose path changed.
     *
     * @return array{bool, list<FormatError>}
     */
    private function indexRegistry(string $bytes): array
    {
        $registry = Registry::parse($bytes, $this->hub->path, $this->hub->isDemoKind());
        $prioritiesPath = $this->hub->prioritiesPath();
        $priorities = Priorities::parse(is_file($prioritiesPath) ? (string) file_get_contents($prioritiesPath) : '');
        $paths = Project::pluck('path', 'id');

        $rows = [];
        foreach ($registry->projects as $order => $project) {
            if ($paths->has($project['id']) && $paths->get($project['id']) !== $project['path']) {
                SourceFile::where('project_id', $project['id'])->delete();
            }
            $rows[] = [
                ...$project,
                'registry_order' => $order,
                'category_rank' => $priorities->rank($project['category']),
                'folder_found' => is_dir($project['path']),
            ];
        }
        Project::whereNotIn('id', array_column($rows, 'id'))->delete();
        if ($rows !== []) {
            Project::upsert($rows, ['id'], array_keys(array_diff_key($rows[0], ['id' => true])));
        }

        return [false, $registry->errors];
    }

    /** @return array{bool, list<FormatError>} */
    private function indexPriorities(string $bytes): array
    {
        $this->rank(Priorities::parse($bytes));

        return [false, []];
    }

    private function rank(Priorities $priorities): void
    {
        foreach (Project::distinct()->pluck('category') as $category) {
            Project::where('category', $category)->update(['category_rank' => $priorities->rank($category)]);
        }
    }

    /**
     * In a file with errors, a repeated task number indexes only its first task.
     *
     * @return array{bool, list<FormatError>}
     */
    private function indexTasks(SourceFile $file, string $bytes): array
    {
        $parsed = TasksFile::parse($bytes);
        $file->tasks()->delete();

        $rows = [];
        $positions = [];
        foreach ($parsed->tasks as $task) {
            $position = $positions[$task->section->value] = ($positions[$task->section->value] ?? -1) + 1;
            if (isset($rows[$task->line->number])) {
                continue;
            }
            $metadata = $task->line->metadata;
            $from = Section::tryFrom($metadata->get('from') ?? '');
            $rows[$task->line->number] = [
                'project_id' => $file->project_id,
                'source_file_id' => $file->id,
                'task_id' => $task->line->id,
                'number' => $task->line->number,
                'section' => $task->section->value,
                'position' => $position,
                'checked' => $task->line->checked,
                'title' => $task->line->title,
                'metadata' => json_encode($metadata->pairs, JSON_THROW_ON_ERROR),
                'due' => self::date($metadata->get('due')),
                'started' => self::date($metadata->get('started')),
                'since' => self::date($metadata->get('since')),
                'done' => self::date($metadata->get('done')),
                'cancelled' => self::date($metadata->get('cancelled')),
                'waiting' => $metadata->get('waiting'),
                'evidence' => $metadata->get('evidence'),
                'from_section' => $from?->isOpen() ? $from->value : null,
                'proof' => $task->proof(),
                'notes' => json_encode($task->notes(), JSON_THROW_ON_ERROR),
                'notes_text' => implode("\n", $task->notes()),
                'line_start' => $task->start + 1,
                'line_end' => $task->end + 1,
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            Task::insert($chunk);
        }

        return [$parsed->isEditable(), [...$parsed->lines->errors, ...$parsed->errors]];
    }

    /**
     * A stage or date that isn't valid (only possible in a file with errors) is stored as null.
     *
     * @return array{bool, list<FormatError>}
     */
    private function indexPipeline(SourceFile $file, string $bytes): array
    {
        $parsed = PipelineFile::parse($bytes);
        $file->pipelineRows()->delete();

        PipelineRow::insert(array_map(fn (ParsedRow $row): array => [
            'source_file_id' => $file->id,
            'project_id' => $file->project_id,
            'position' => $row->position,
            'company' => $row->company,
            'role' => $row->role,
            'stage' => Stage::tryFrom($row->stage)?->value,
            'next_action' => $row->nextAction,
            'date' => self::date($row->date),
        ], $parsed->rows));

        return [$parsed->isEditable(), [...$parsed->lines->errors, ...$parsed->errors]];
    }

    /**
     * Stores this version of the file (seeing it again only refreshes last_seen_at), then keeps the
     * most recently seen versions and every version an open conflict refers to.
     */
    private function keepVersion(string $path, string $pathHash, string $hash, string $bytes): void
    {
        FileVersion::upsert(
            [['path' => $path, 'path_hash' => $pathHash, 'hash' => $hash, 'content' => $bytes, 'last_seen_at' => now()]],
            ['path_hash', 'hash'],
            ['last_seen_at'],
        );

        $recent = FileVersion::where('path_hash', $pathHash)
            ->orderByDesc('last_seen_at')->orderByDesc('id')
            ->limit(self::VERSIONS_KEPT)->pluck('id');
        $referenced = Conflict::where('path_hash', $pathHash)->where('status', 'open')
            ->get(['base_hash', 'disk_hash'])
            ->flatMap(fn (Conflict $conflict): array => [$conflict->base_hash, $conflict->disk_hash]);

        FileVersion::where('path_hash', $pathHash)
            ->whereNotIn('id', $recent)
            ->whereNotIn('hash', $referenced)
            ->delete();
    }

    /** A write's temp file older than a minute was left by a crash. */
    private function removeStaleTempFiles(string $folder): void
    {
        foreach (glob($folder.'/.*.markboard-*.tmp') ?: [] as $temp) {
            if (@filemtime($temp) < now()->getTimestamp() - 60) {
                @unlink($temp);
            }
        }
    }

    private static function date(?string $value): ?string
    {
        return $value !== null && TasksFile::isDate($value) ? $value : null;
    }
}
