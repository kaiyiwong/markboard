<?php

namespace App\Markdown;

/**
 * A parsed TASKS.md: its lines, section headings, task blocks and errors.
 *
 * The loop in parse() follows check-tasks.py's parse() step by step, with the same error messages,
 * so the two report the same errors on the same lines. Parsing is best-effort: a file with errors
 * still yields every task it can read, but only a file with no errors at all is editable.
 */
final readonly class TasksFile
{
    private const string INDENTED = '/\A  (proof|note): [^'.Text::SPACE.']/u';

    /**
     * @param  array<string, int>  $headings  section heading line index, keyed by section name
     * @param  list<TaskBlock>  $tasks  in file order
     * @param  list<FormatError>  $errors  format errors, as check-tasks.py reports them
     */
    private function __construct(
        public Lines $lines,
        public array $headings,
        public array $tasks,
        public array $errors,
    ) {}

    public static function parse(string $bytes): self
    {
        $lines = Lines::split($bytes);
        $errors = [];
        $headings = [];
        $seen = [];
        $current = null;
        /** @var list<array{section: Section, start: int, end: int, line: TaskLine, proofs: list<array{index: int, text: string}>, notes: list<array{index: int, text: string}>}> $tasks */
        $tasks = [];
        $last = null;
        $firstLineOf = [];

        $error = function (int $line, string $message) use (&$errors): void {
            $errors[] = new FormatError($line, $message);
        };

        foreach ($lines->lines as $index => $line) {
            $n = $index + 1;
            $text = mb_scrub($line->content, 'UTF-8');

            if (str_starts_with($text, '## ')) {
                $name = Text::strip(substr($text, 3));
                $last = null;
                $section = Section::tryFrom($name);
                if ($section === null) {
                    if ($current !== null) {
                        $error($n, "unknown section \"{$name}\"");
                    }

                    continue;
                }
                if (in_array($section, $seen, true)) {
                    $error($n, "section \"{$name}\" appears twice");
                }
                $seen[] = $section;
                $current = $section;
                $headings[$name] ??= $index;

                continue;
            }

            if ($current === null || Text::strip($text) === '') {
                continue;
            }

            if (str_starts_with($text, ' ')) {
                if (! preg_match(self::INDENTED, $text)) {
                    $error($n, 'indented line must be "  proof: ..." or "  note: ..."');
                } elseif ($last === null) {
                    $error($n, 'indented line is not under a task');
                } else {
                    [$kind, $value] = explode(': ', Text::strip($text), 2);
                    $tasks[$last][$kind === 'proof' ? 'proofs' : 'notes'][] = ['index' => $index, 'text' => $value];
                    $tasks[$last]['end'] = $index;
                }

                continue;
            }

            $task = TaskLine::parse($text);
            if ($task === null) {
                $error($n, 'text between sections');
                $last = null;

                continue;
            }

            if (isset($firstLineOf[$task->number])) {
                $error($n, "duplicate ID {$task->id} (first on line {$firstLineOf[$task->number]})");
            } else {
                $firstLineOf[$task->number] = $n;
            }

            $keys = [];
            foreach ($task->metadata->pairs as [$key, $value]) {
                if (in_array($key, $keys, true)) {
                    $error($n, "metadata key \"{$key}\" appears twice");
                }
                if ($value === '') {
                    $error($n, "metadata key \"{$key}\" has no value");
                } elseif (in_array($key, Metadata::DATE_KEYS, true) && ! self::isDate($value)) {
                    $error($n, "bad date for \"{$key}\": {$value}");
                } elseif ($key === 'from' && ! Section::tryFrom($value)?->isOpen()) {
                    $error($n, "\"from\" must be Up next, In progress or Waiting on, not \"{$value}\"");
                }
                $keys[] = $key;
            }

            if ($current === Section::Done) {
                if (! $task->checked) {
                    $error($n, '[ ] in Done: every task in Done is [x]');
                }
                if (in_array('cancelled', $keys, true) && in_array('done', $keys, true)) {
                    $error($n, 'a cancelled task has no done date');
                } elseif (! in_array('cancelled', $keys, true) && ! in_array('done', $keys, true)) {
                    $error($n, 'Done task needs done (or cancelled)');
                }
            } else {
                if ($task->checked) {
                    $error($n, '[x] outside Done');
                }
                if ($current === Section::InProgress && ! in_array('started', $keys, true)) {
                    $error($n, 'In progress task needs started');
                }
                if ($current === Section::WaitingOn) {
                    foreach (['waiting', 'since'] as $required) {
                        if (! in_array($required, $keys, true)) {
                            $error($n, "Waiting on task needs {$required}");
                        }
                    }
                }
            }

            $tasks[] = ['section' => $current, 'start' => $index, 'end' => $index, 'line' => $task, 'proofs' => [], 'notes' => []];
            $last = count($tasks) - 1;
        }

        $all = Section::cases();
        if ($seen !== $all) {
            $missing = array_filter($all, fn (Section $section): bool => ! in_array($section, $seen, true));
            if ($missing !== []) {
                $error(0, 'missing section(s): '.self::names($missing));
            } elseif (count($seen) === count($all)) {
                $error(0, 'sections out of order: '.self::names($seen));
            }
        }

        usort($errors, fn (FormatError $a, FormatError $b): int => $a->line <=> $b->line);

        return new self(
            $lines,
            $headings,
            array_map(fn (array $task): TaskBlock => new TaskBlock(...$task), $tasks),
            $errors,
        );
    }

    /** Python's valid_date(): YYYY-MM-DD and a real calendar date. */
    public static function isDate(string $value): bool
    {
        return preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $value, $m) === 1
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /** Only a file with no format errors and no file errors (encoding, line breaks) is ever written. */
    public function isEditable(): bool
    {
        return $this->errors === [] && $this->lines->errors === [];
    }

    /** The first task with this number (`T9` and `T09` are the same task). */
    public function task(int $number): ?TaskBlock
    {
        foreach ($this->tasks as $task) {
            if ($task->line->number === $number) {
                return $task;
            }
        }

        return null;
    }

    /** @return list<TaskBlock> */
    public function tasksIn(Section $section): array
    {
        return array_values(array_filter($this->tasks, fn (TaskBlock $task): bool => $task->section === $section));
    }

    public function headingIndex(Section $section): ?int
    {
        return $this->headings[$section->value] ?? null;
    }

    /**
     * The free text before the first section heading.
     *
     * @return list<Line>
     */
    public function preamble(): array
    {
        return array_slice($this->lines->lines, 0, $this->headings === [] ? null : min($this->headings));
    }

    /** IDs are never reused: the next is the highest number plus 1. */
    public function nextNumber(): int
    {
        return max([0, ...array_map(fn (TaskBlock $task): int => $task->line->number, $this->tasks)]) + 1;
    }

    /** @param  array<Section>  $sections */
    private static function names(array $sections): string
    {
        return implode(', ', array_map(fn (Section $section): string => $section->value, $sections));
    }
}
