<?php

namespace App\Night;

/** One status.tsv row: what happened to one task in one run. */
final readonly class StatusRow
{
    public function __construct(
        public string $project,
        public string $task,
        /** ready, blocked, failed, stuck or skipped from the runner; merged or discarded from Markboard. */
        public string $outcome,
        /** YYYY-MM-DD HH:MM, the hub's local time. */
        public string $time,
        /** The branch head the outcome is about, short; empty when there is none. */
        public string $commit,
        public string $reason,
    ) {}

    /** How many builder rounds the runner's reason says it took (1 when it doesn't say). */
    public function rounds(): int
    {
        return preg_match('/over (\d+) round/', $this->reason, $match) === 1 ? (int) $match[1] : 1;
    }
}
