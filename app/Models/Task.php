<?php

namespace App\Models;

use App\Markdown\Section;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A task as sync last read it. The date and string columns are copied out of the metadata so
 * they can be filtered; the metadata itself keeps every pair as written.
 */
#[Table(timestamps: false)]
#[Unguarded]
class Task extends Model
{
    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<SourceFile, $this> */
    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(SourceFile::class);
    }

    /**
     * Tasks whose title, proof or notes contain every word of the query. The query is split at every
     * character that isn't a letter or digit, so no boolean-mode operator reaches MySQL. MySQL uses
     * the FULLTEXT index, matching each word of 3 or more characters as a prefix; SQLite (local
     * tests) has no such index and matches each word anywhere with LIKE.
     *
     * @param  Builder<Task>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $terms): void
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $terms, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($this->getConnection()->getDriverName() === 'sqlite') {
            foreach ($words as $word) {
                $query->where(fn (Builder $any) => $any
                    ->whereLike('title', "%{$word}%")
                    ->orWhereLike('proof', "%{$word}%")
                    ->orWhereLike('notes_text', "%{$word}%"));
            }

            return;
        }

        $words = array_filter($words, fn (string $word): bool => mb_strlen($word) >= 3);
        if ($words === []) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->whereFullText(['title', 'proof', 'notes_text'], implode(' ', array_map(fn (string $word): string => "+{$word}*", $words)), ['mode' => 'boolean']);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'section' => Section::class,
            'position' => 'integer',
            'checked' => 'boolean',
            'metadata' => 'array',
            'due' => 'date',
            'started' => 'date',
            'since' => 'date',
            'done' => 'date',
            'cancelled' => 'date',
            'from_section' => Section::class,
            'notes' => 'array',
            'line_start' => 'integer',
            'line_end' => 'integer',
        ];
    }
}
