<?php

namespace App\Markdown;

/**
 * One line of a file: its text and the line break that ended it ('' for a last line with none).
 */
final readonly class Line
{
    public function __construct(
        public string $content,
        public string $terminator,
    ) {}

    public function bytes(): string
    {
        return $this->content.$this->terminator;
    }
}
