<?php

namespace App\Markdown;

/**
 * A brief (TODAY.md or briefs/YYYY-MM-DD.md) split into its title and its `##` sections, so the
 * Brief page can show each section on its own surface. The Markdown itself is kept as written;
 * each part is rendered separately. A `#` line inside a fenced code block is never a heading.
 */
final readonly class Brief
{
    /**
     * @param  string|null  $title  the first `# ` heading's text
     * @param  string  $intro  the Markdown before the first `## ` heading, title line left out
     * @param  list<array{title: string, markdown: string, items: int}>  $sections  in file order; items counts the section's top-level list items
     */
    private function __construct(
        public ?string $title,
        public string $intro,
        public array $sections,
    ) {}

    public static function parse(string $markdown): self
    {
        $title = null;
        $intro = [];
        $sections = [];
        $fence = null;

        foreach (preg_split('/\r\n|\n|\r/', $markdown) ?: [] as $line) {
            $wasInFence = $fence !== null;
            if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $match) === 1) {
                $fence = $fence === null ? $match[1][0] : ($match[1][0] === $fence ? null : $fence);
            }
            if ($fence === null && $title === null && $sections === [] && preg_match('/^# +(.+?)\s*#*\s*$/', $line, $match) === 1) {
                $title = $match[1];

                continue;
            }
            if ($fence === null && preg_match('/^## +(.+?)\s*#*\s*$/', $line, $match) === 1) {
                $sections[] = ['title' => $match[1], 'lines' => [], 'items' => 0];

                continue;
            }
            if ($sections === []) {
                $intro[] = $line;
            } else {
                $last = array_key_last($sections);
                $sections[$last]['lines'][] = $line;
                $sections[$last]['items'] += ! $wasInFence && preg_match('/^(?:[-*+]|[0-9]+[.)]) +\S/', $line) === 1 ? 1 : 0;
            }
        }

        return new self($title, trim(implode("\n", $intro)), array_map(fn (array $section): array => [
            'title' => $section['title'],
            'markdown' => trim(implode("\n", $section['lines'])),
            'items' => $section['items'],
        ], $sections));
    }
}
