<?php

namespace App\Night;

/**
 * The verifier's verdict (`orders/<project>/<task>.verdict.md`): line 1 PASS, FAIL or INCONCLUSIVE,
 * line 2 the commit it judged, then a summary, `## Items` (`- RESULT: item (evidence)`),
 * `## Scope problems` and `## Look at`.
 */
final readonly class Verdict
{
    /**
     * @param  list<array{result: string, item: string, evidence: string}>  $items
     * @param  list<string>  $scopeProblems
     * @param  list<string>  $lookAt
     */
    public function __construct(
        public string $result,
        public string $commit,
        public string $summary,
        public array $items,
        public array $scopeProblems,
        public array $lookAt,
    ) {}

    public static function parse(string $text): self
    {
        $lines = preg_split('/\r\n|\n|\r/', $text) ?: [];
        $result = trim($lines[0] ?? '');
        $result = in_array($result, ['PASS', 'FAIL', 'INCONCLUSIVE'], true) ? $result : 'INCONCLUSIVE';
        $commit = trim($lines[1] ?? '');
        $summary = [];
        $section = null;
        $items = $scope = $look = [];
        foreach (array_slice($lines, 2) as $line) {
            if (preg_match('/^## +(.+?)\s*$/', $line, $match) === 1) {
                $section = $match[1];

                continue;
            }
            if ($section === null) {
                $summary[] = $line;

                continue;
            }
            if (! str_starts_with($line, '- ')) {
                continue;
            }
            $bullet = substr($line, 2);
            if ($section === 'Items' && preg_match('/^(PASS|FAIL|INCONCLUSIVE): (.*)$/', $bullet, $match) === 1) {
                [$item, $evidence] = self::splitEvidence($match[2]);
                $items[] = ['result' => $match[1], 'item' => $item, 'evidence' => $evidence];
            } elseif ($section === 'Scope problems') {
                $scope[] = $bullet;
            } elseif ($section === 'Look at') {
                $look[] = $bullet;
            }
        }

        return new self($result, $commit, trim(implode("\n", $summary)), $items, $scope, $look);
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
