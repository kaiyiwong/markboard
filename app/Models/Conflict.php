<?php

namespace App\Models;

use App\Actions\FileEdit;
use App\Actions\Operation;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * An edit refused because the file changed since the user saw it (a 412). History, not index:
 * it is keyed by path and survives `markboard:sync --fresh`.
 */
#[Unguarded]
class Conflict extends Model
{
    public const string OPEN = 'open';

    public const string RESOLVED = 'resolved';

    public const string SUPERSEDED = 'superseded';

    /**
     * The file's unresolved conflicts, oldest first: the page shows them above its tasks or board.
     *
     * @return Collection<int, self>
     */
    public static function openOn(SourceFile $file): Collection
    {
        return self::where('path_hash', $file->path_hash)->where('status', self::OPEN)->oldest('id')->get();
    }

    /** The refused edit, rebuilt from what was stored. */
    public function edit(): FileEdit
    {
        return $this->operation->edit($this->parameters);
    }

    /** The version the user edited, if file_versions still holds it. */
    public function base(): ?string
    {
        return $this->versionContent($this->base_hash);
    }

    /** A version of the file by its hash, if file_versions still holds it. */
    public function versionContent(?string $hash): ?string
    {
        $content = FileVersion::where('path_hash', $this->path_hash)->where('hash', $hash)->value('content');

        return is_string($content) ? $content : null;
    }

    /**
     * Whether the edit can be applied to this version: the base version is still stored, and the
     * lines the edit depends on are the same in both (docs/spec.md, Conflicts).
     */
    public function canApplyTo(string $bytes): bool
    {
        $base = $this->base();
        if ($base === null) {
            return false;
        }
        $edit = $this->edit();
        $before = $edit->dependsOn($base);

        return $before !== null && $before === $edit->dependsOn($bytes);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'operation' => Operation::class,
            'parameters' => 'array',
        ];
    }
}
