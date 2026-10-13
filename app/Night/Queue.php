<?php

namespace App\Night;

/** The night run's `orders/queue.txt`: `<project> <task> [model] [effort]` lines, `#` starts a comment. */
final class Queue
{
    /** @return list<array{project: string, task: string, model: string, effort: string}> */
    public static function parse(string $text): array
    {
        $items = [];
        foreach (preg_split('/\r\n|\n|\r/', $text) ?: [] as $line) {
            $parts = preg_split('/\s+/', trim(explode('#', $line, 2)[0]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($parts) >= 2) {
                $items[] = ['project' => $parts[0], 'task' => $parts[1], 'model' => $parts[2] ?? 'opus', 'effort' => $parts[3] ?? 'high'];
            }
        }

        return $items;
    }
}
