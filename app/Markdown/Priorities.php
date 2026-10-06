<?php

namespace App\Markdown;

/**
 * A parsed priorities.md. Only its `Order:` line is read, for example
 * `Order: own-site, client, job, product = game = gen-ai`: categories joined by `=` rank equal,
 * and categories it doesn't name rank after all the named ones.
 */
final readonly class Priorities
{
    /**
     * @param  array<string, int>  $ranks  0-based rank, keyed by category
     */
    private function __construct(
        public array $ranks,
    ) {}

    /** With no `Order:` line (or no file: pass ''), every category ranks equal. */
    public static function parse(string $bytes): self
    {
        foreach (Lines::split($bytes)->lines as $line) {
            $text = Text::strip(mb_scrub($line->content, 'UTF-8'));
            if (! str_starts_with($text, 'Order:')) {
                continue;
            }
            $ranks = [];
            foreach (explode(',', substr($text, strlen('Order:'))) as $rank => $group) {
                foreach (explode('=', $group) as $category) {
                    $ranks[Text::strip($category)] ??= $rank;
                }
            }
            unset($ranks['']);

            return new self($ranks);
        }

        return new self([]);
    }

    public function rank(string $category): int
    {
        return $this->ranks[$category] ?? ($this->ranks === [] ? 0 : max($this->ranks) + 1);
    }
}
