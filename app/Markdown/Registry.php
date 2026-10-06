<?php

namespace App\Markdown;

/**
 * A parsed projects.md: the hub's registry, one table row per project.
 *
 * Rows that break a rule are skipped and reported as errors at their line, so the Projects page
 * can list them. A repeated id or path keeps the first row. Markboard never writes this file.
 */
final readonly class Registry
{
    /** The header's names; the file may put them in any order. */
    public const array COLUMNS = ['id', 'name', 'path', 'category', 'status', 'next milestone', 'docs'];

    public const array CATEGORIES = ['own-site', 'client', 'job', 'product', 'game', 'gen-ai', 'personal'];

    public const array STATUSES = ['active', 'paused', 'done'];

    /** Ids are keys in URLs and the database, so they are kept short. */
    public const int ID_LENGTH = 64;

    /**
     * The projects are in file order, with relative paths resolved.
     *
     * @param  list<array{id: string, name: string, path: string, category: string, status: string, next_milestone: string, docs: string}>  $projects
     * @param  list<FormatError>  $errors  the malformed and duplicate rows, and problems with the table itself
     */
    private function __construct(
        public array $projects,
        public array $errors,
    ) {}

    /**
     * @param  string  $hubPath  relative paths are resolved against it
     * @param  bool  $demoKind  only a demo-kind hub may use relative paths
     */
    public static function parse(string $bytes, string $hubPath, bool $demoKind): self
    {
        $table = Table::find(Lines::split($bytes), self::COLUMNS);
        if ($table === null) {
            return new self([], [new FormatError(0, 'no table with the columns '.implode(', ', self::COLUMNS))]);
        }

        $errors = [];
        if (! $table->hasSeparator) {
            $errors[] = new FormatError($table->headerIndex + 1, 'no separator line under the table header');
        }

        $projects = [];
        $lineOfId = [];
        $lineOfPath = [];
        foreach ($table->rows as ['index' => $index, 'cells' => $cells]) {
            $n = $index + 1;
            if (count($cells) !== count($table->columns)) {
                $errors[] = new FormatError($n, sprintf('row has %d %s, the header has %d', count($cells), count($cells) === 1 ? 'cell' : 'cells', count($table->columns)));

                continue;
            }
            $row = array_combine($table->columns, $cells);
            $path = $row['path'];
            $relative = $path !== '' && ! str_starts_with($path, '/');

            $problem = match (true) {
                preg_match('/\A[a-z0-9]+(-[a-z0-9]+)*\z/', $row['id']) !== 1 => "id \"{$row['id']}\" is not kebab-case",
                strlen($row['id']) > self::ID_LENGTH => 'id is longer than '.self::ID_LENGTH.' characters',
                ! in_array($row['category'], self::CATEGORIES, true) => "category \"{$row['category']}\" is not one of ".implode(', ', self::CATEGORIES),
                ! in_array($row['status'], self::STATUSES, true) => "status \"{$row['status']}\" is not one of ".implode(', ', self::STATUSES),
                $path === '' => 'path is empty',
                $relative && ! $demoKind => "path \"{$path}\" is relative: only a demo hub may use relative paths",
                default => null,
            };
            if ($problem !== null) {
                $errors[] = new FormatError($n, $problem);

                continue;
            }

            $path = rtrim($relative ? rtrim($hubPath, '/').'/'.$path : $path, '/');
            if (isset($lineOfId[$row['id']])) {
                $errors[] = new FormatError($n, "duplicate id {$row['id']} (first on line {$lineOfId[$row['id']]})");

                continue;
            }
            if (isset($lineOfPath[$path])) {
                $errors[] = new FormatError($n, "duplicate path {$path} (first on line {$lineOfPath[$path]})");

                continue;
            }
            $lineOfId[$row['id']] = $n;
            $lineOfPath[$path] = $n;

            $projects[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'path' => $path,
                'category' => $row['category'],
                'status' => $row['status'],
                'next_milestone' => $row['next milestone'],
                'docs' => $row['docs'],
            ];
        }

        return new self($projects, $errors);
    }
}
