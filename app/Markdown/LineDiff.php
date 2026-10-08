<?php

namespace App\Markdown;

/**
 * A line diff between two versions of a file, for the conflict panel: the changed lines, with a
 * little unchanged context around each change.
 *
 * The common start and end are trimmed first, so a conflict (usually a few changed lines in a
 * long file) leaves only a small middle for the longest-common-subsequence table.
 */
final class LineDiff
{
    /** Unchanged lines kept on each side of a change. */
    private const int CONTEXT = 2;

    /**
     * Each line with its 1-based number in the old version, the new one, or both.
     *
     * @return list<array{op: 'same'|'removed'|'added', old: int|null, new: int|null, text: string}>
     */
    public static function between(string $old, string $new): array
    {
        $a = self::texts($old);
        $b = self::texts($new);

        $start = 0;
        while ($start < count($a) && $start < count($b) && $a[$start] === $b[$start]) {
            $start++;
        }
        $endA = count($a);
        $endB = count($b);
        while ($endA > $start && $endB > $start && $a[$endA - 1] === $b[$endB - 1]) {
            $endA--;
            $endB--;
        }

        $lines = [];
        for ($i = 0; $i < $start; $i++) {
            $lines[] = ['op' => 'same', 'old' => $i + 1, 'new' => $i + 1, 'text' => $a[$i]];
        }
        array_push($lines, ...self::middle(array_slice($a, $start, $endA - $start), array_slice($b, $start, $endB - $start), $start));
        for ($i = $endA, $j = $endB; $i < count($a); $i++, $j++) {
            $lines[] = ['op' => 'same', 'old' => $i + 1, 'new' => $j + 1, 'text' => $a[$i]];
        }

        return self::withContext($lines);
    }

    /**
     * The lines' text, made valid UTF-8 so the diff can always be sent as JSON.
     *
     * @return list<string>
     */
    private static function texts(string $bytes): array
    {
        return array_map(fn (Line $line): string => mb_scrub($line->content, 'UTF-8'), Lines::split($bytes)->lines);
    }

    /**
     * The longest-common-subsequence diff of the part that differs.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{op: 'same'|'removed'|'added', old: int|null, new: int|null, text: string}>
     */
    private static function middle(array $a, array $b, int $offset): array
    {
        $n = count($a);
        $m = count($b);
        // $lcs[$i][$j]: the longest common subsequence of $a from $i and $b from $j.
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $lines = [];
        $i = 0;
        $j = 0;
        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && $a[$i] === $b[$j]) {
                $lines[] = ['op' => 'same', 'old' => $offset + $i + 1, 'new' => $offset + $j + 1, 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($j < $m && ($i === $n || $lcs[$i][$j + 1] > $lcs[$i + 1][$j])) {
                $lines[] = ['op' => 'added', 'old' => null, 'new' => $offset + $j + 1, 'text' => $b[$j]];
                $j++;
            } else {
                $lines[] = ['op' => 'removed', 'old' => $offset + $i + 1, 'new' => null, 'text' => $a[$i]];
                $i++;
            }
        }

        return $lines;
    }

    /**
     * Keeps every change and the unchanged lines within CONTEXT of one.
     *
     * @param  list<array{op: 'same'|'removed'|'added', old: int|null, new: int|null, text: string}>  $lines
     * @return list<array{op: 'same'|'removed'|'added', old: int|null, new: int|null, text: string}>
     */
    private static function withContext(array $lines): array
    {
        $changed = array_keys(array_filter($lines, fn (array $line): bool => $line['op'] !== 'same'));

        return array_values(array_filter($lines, function (array $line, int $index) use ($changed): bool {
            foreach ($changed as $change) {
                if (abs($change - $index) <= self::CONTEXT) {
                    return true;
                }
            }

            return false;
        }, ARRAY_FILTER_USE_BOTH));
    }
}
