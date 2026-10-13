<?php

namespace App\Night;

/**
 * The night run's `orders/status.tsv`: one row per task per run, tab-separated, written by the hub's
 * runner (and by Markboard for merged and discarded). A night runs from 21:00 to 21:00 the next day,
 * and a task's latest row in that window is its state (docs/spec.md, Night).
 */
final readonly class StatusLog
{
    /** @param  list<StatusRow>  $rows  in file order */
    public function __construct(public array $rows) {}

    public static function parse(string $text): self
    {
        $rows = [];
        foreach (preg_split('/\r\n|\n|\r/', $text) ?: [] as $line) {
            $cells = explode("\t", $line);
            if (count($cells) < 6 || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $cells[3]) !== 1) {
                continue;
            }
            [$project, $task, $outcome, $time, $commit] = $cells;
            $rows[] = new StatusRow($project, $task, $outcome, $time, $commit, implode("\t", array_slice($cells, 5)));
        }

        return new self($rows);
    }

    /** The night a time belongs to: 21:00 on D up to 21:00 on D+1 is the night of D. */
    public static function nightOf(string $time): string
    {
        $date = substr($time, 0, 10);

        return substr($time, 11, 5) >= '21:00' ? $date : date('Y-m-d', (int) strtotime($date.' -1 day'));
    }

    /**
     * The nights that have rows, newest first.
     *
     * @return list<string>
     */
    public function nights(): array
    {
        $nights = array_values(array_unique(array_map(fn (StatusRow $row): string => self::nightOf($row->time), $this->rows)));
        rsort($nights);

        return $nights;
    }

    /**
     * Each task's latest row in the night, in the order the tasks first appear.
     *
     * @return list<StatusRow>
     */
    public function night(string $date): array
    {
        $latest = [];
        foreach ($this->rows as $row) {
            if (self::nightOf($row->time) === $date) {
                $latest[$row->project."\t".$row->task] = $row;
            }
        }

        return array_values($latest);
    }

    /** A task's latest row in any night, or null. */
    public function latest(string $project, string $task): ?StatusRow
    {
        $found = null;
        foreach ($this->rows as $row) {
            if ($row->project === $project && $row->task === $task) {
                $found = $row;
            }
        }

        return $found;
    }

    /** A row as a status.tsv line, for appending. */
    public static function line(StatusRow $row): string
    {
        $clean = fn (string $value): string => str_replace(["\t", "\r", "\n"], ' ', $value);

        return implode("\t", array_map($clean, [$row->project, $row->task, $row->outcome, $row->time, $row->commit, $row->reason]))."\n";
    }
}
