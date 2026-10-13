<?php

namespace App\Night;

/**
 * The verifier's verdict (`orders/<project>/<task>.verdict.md`): line 1 PASS, FAIL or INCONCLUSIVE,
 * line 2 the commit it judged, then a summary, `## Items` (`- RESULT: item (evidence)`),
 * `## Scope problems` and `## Look at`.
 *
 * Optional, for the review card (docs/spec.md, Night): `## Headline` (one line saying what changed),
 * `short:` and `says:` lines indented under an item (its name and its evidence in a few words), and
 * `## Confirm: <title>` blocks of things to check by eye: a `columns: a | b` line, `### group`
 * headings, `- a | b` rows and a `note:` line. A verdict without them reads as before.
 */
final readonly class Verdict
{
    /**
     * @param  list<array{result: string, item: string, evidence: string, short: string, says: string}>  $items
     * @param  list<string>  $scopeProblems
     * @param  list<string>  $lookAt
     * @param  list<array{title: string, columns: list<string>, groups: list<array{label: string, rows: list<list<string>>}>, note: string}>  $confirm
     */
    public function __construct(
        public string $result,
        public string $commit,
        public string $summary,
        public array $items,
        public array $scopeProblems,
        public array $lookAt,
        public string $headline = '',
        public array $confirm = [],
    ) {}

    public static function parse(string $text): self
    {
        $lines = preg_split('/\r\n|\n|\r/', $text) ?: [];
        $result = trim($lines[0] ?? '');
        $result = in_array($result, ['PASS', 'FAIL', 'INCONCLUSIVE'], true) ? $result : 'INCONCLUSIVE';
        $commit = trim($lines[1] ?? '');
        $summary = [];
        $section = null;
        $items = $scope = $look = $confirm = [];
        $headline = '';
        foreach (array_slice($lines, 2) as $line) {
            if (preg_match('/^## +(.+?)\s*$/', $line, $match) === 1) {
                $section = $match[1];
                if (preg_match('/^Confirm: *(.+)$/', $section, $title) === 1) {
                    $confirm[] = ['title' => $title[1], 'columns' => [], 'groups' => [], 'note' => ''];
                    $section = 'Confirm';
                }

                continue;
            }
            if ($section === null) {
                $summary[] = $line;

                continue;
            }
            if ($section === 'Headline') {
                $headline = $headline === '' ? trim($line) : $headline;

                continue;
            }
            if ($section === 'Confirm') {
                $last = array_key_last($confirm);
                $confirm[$last] = self::confirmLine($confirm[$last], $line);

                continue;
            }
            if ($section === 'Items' && $items !== [] && preg_match('/^\s+(short|says): *(.*?)\s*$/', $line, $match) === 1) {
                $items[array_key_last($items)][$match[1]] = $match[2];

                continue;
            }
            if (! str_starts_with($line, '- ')) {
                continue;
            }
            $bullet = substr($line, 2);
            if ($section === 'Items' && preg_match('/^(PASS|FAIL|INCONCLUSIVE): (.*)$/', $bullet, $match) === 1) {
                [$item, $evidence] = self::splitEvidence($match[2]);
                $items[] = ['result' => $match[1], 'item' => $item, 'evidence' => $evidence, 'short' => '', 'says' => ''];
            } elseif ($section === 'Scope problems') {
                $scope[] = $bullet;
            } elseif ($section === 'Look at') {
                $look[] = $bullet;
            }
        }

        return new self($result, $commit, trim(implode("\n", $summary)), $items, $scope, $look, $headline, $confirm);
    }

    /**
     * A `## Confirm:` block with one more of its lines read into it.
     *
     * @param  array{title: string, columns: list<string>, groups: list<array{label: string, rows: list<list<string>>}>, note: string}  $block
     * @return array{title: string, columns: list<string>, groups: list<array{label: string, rows: list<list<string>>}>, note: string}
     */
    private static function confirmLine(array $block, string $line): array
    {
        $cells = static fn (string $text): array => array_map(trim(...), explode(' | ', $text));
        if (preg_match('/^columns: *(.+)$/', $line, $match) === 1) {
            $block['columns'] = $cells($match[1]);
        } elseif (preg_match('/^note: *(.+)$/', $line, $match) === 1) {
            $block['note'] = trim($match[1]);
        } elseif (preg_match('/^### +(.+?)\s*$/', $line, $match) === 1) {
            $block['groups'][] = ['label' => $match[1], 'rows' => []];
        } elseif (str_starts_with($line, '- ')) {
            $groups = $block['groups'] === [] ? [['label' => '', 'rows' => []]] : $block['groups'];
            $last = array_pop($groups);
            $last['rows'][] = $cells(substr($line, 2));
            $groups[] = $last;
            $block['groups'] = $groups;
        }

        return $block;
    }

    /**
     * "item (evidence)": the evidence is the last parenthesis, matched from the end, so an item or
     * evidence that has parentheses of its own stays whole.
     *
     * @return array{string, string}
     */
    private static function splitEvidence(string $text): array
    {
        $text = rtrim($text);
        if (! str_ends_with($text, ')')) {
            return [$text, ''];
        }
        $depth = 0;
        for ($i = strlen($text) - 1; $i >= 0; $i--) {
            $depth += match ($text[$i]) {
                ')' => 1,
                '(' => -1,
                default => 0,
            };
            if ($depth === 0) {
                return $i > 0 && $text[$i - 1] === ' '
                    ? [rtrim(substr($text, 0, $i)), substr($text, $i + 1, -1)]
                    : [$text, ''];
            }
        }

        return [$text, ''];
    }
}
