<?php

namespace App\Actions;

use App\Models\SourceFile;
use App\Sync\SourceKind;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One edit to one TASKS.md or pipeline.md, run by WriteFile (the write path). Each operation is one
 * class. Its public constructor arguments are its parameters: a refused edit is stored on its
 * conflict as the operation and those arguments, and rebuilt from them to be applied later.
 */
interface FileEdit
{
    public function operation(): Operation;

    public function kind(): SourceKind;

    /** A short description for the conflict panel, such as "Tick T12". */
    public function summary(): string;

    /**
     * The file's new bytes. Throws FileNotEditable, TaskNotFound or RowNotFound, or EditRefused
     * when the edit can't be made to the task or row as it stands.
     */
    public function apply(string $bytes, DateTimeInterface $today): string;

    /**
     * The part of a version of the file this edit depends on, or null if its task or row isn't in it.
     * A conflict can be applied when this is the same in the version the user saw and the one on disk.
     */
    public function dependsOn(string $bytes): ?string;

    /** The task or row as the index holds it now, for a conflict; null for an Add, or if it's gone. */
    public function current(SourceFile $file): ?JsonResource;

    /** The task or row the edit wrote, read from the index after the write. */
    public function result(SourceFile $file): JsonResource;
}
