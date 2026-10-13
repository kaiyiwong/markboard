<?php

namespace App\Night;

/**
 * A work order (`orders/<project>/<task>.md`): what the chief of staff hands a builder, or, with
 * `status: needs-kai`, the questions it needs Kai to answer first. Kai's answers go in a
 * `## KAI'S ANSWERS` section, which the next triage reads.
 */
final readonly class WorkOrder
{
    public const string ANSWERS = "KAI'S ANSWERS";

    /** @param  array<string, string>  $sections  each `## ` section's markdown, by heading */
    public function __construct(
        public ?string $title,
        public string $status,
        public array $sections,
    ) {}

    public static function parse(string $text): self
    {
        $title = null;
        $status = '';
        $sections = [];
        $current = null;
        foreach (preg_split('/\r\n|\n|\r/', $text) ?: [] as $line) {
            if ($title === null && $current === null && preg_match('/^# +(.+?)\s*$/', $line, $match) === 1) {
                $title = $match[1];
            } elseif ($current === null && preg_match('/^status:\s*(\S+)/', $line, $match) === 1) {
                $status = $match[1];
            } elseif (preg_match('/^## +(.+?)\s*$/', $line, $match) === 1) {
                $current = $match[1];
                $sections[$current] = '';
            } elseif ($current !== null) {
                $sections[$current] .= $line."\n";
            }
        }

        return new self($title, $status, array_map('trim', $sections));
    }

    public function needsKai(): bool
    {
        return $this->status === 'needs-kai';
    }

    /**
     * The order with Kai's answers as its `## KAI'S ANSWERS` section, replacing an earlier one in
     * place, or added at the end. Every other byte of the file stays as it was.
     */
    public static function withAnswers(string $text, string $answers): string
    {
        $block = '## '.self::ANSWERS."\n\n".trim($answers)."\n";
        $lines = preg_split('/(?<=\n)/', $text) ?: [];
        $start = null;
        foreach ($lines as $i => $line) {
            if (rtrim($line, "\r\n") === '## '.self::ANSWERS) {
                $start = $i;
                break;
            }
        }
        if ($start === null) {
            $text = $text === '' || str_ends_with($text, "\n") ? $text : $text."\n";

            return $text.($text === '' ? '' : "\n").$block;
        }
        $end = count($lines);
        for ($i = $start + 1; $i < count($lines); $i++) {
            if (str_starts_with($lines[$i], '## ')) {
                $end = $i;
                break;
            }
        }
        $after = array_slice($lines, $end);

        return implode('', array_slice($lines, 0, $start)).$block.($after === [] ? '' : "\n".implode('', $after));
    }
}
