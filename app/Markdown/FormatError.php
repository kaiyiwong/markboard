<?php

namespace App\Markdown;

/**
 * A problem in a file, at a 1-based line number (0 for the file as a whole), worded as check-tasks.py words it.
 */
final readonly class FormatError
{
    public function __construct(
        public int $line,
        public string $message,
    ) {}
}
