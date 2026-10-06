<?php

namespace App\Markdown;

use RuntimeException;

/**
 * No task with this number is in the file.
 */
final class TaskNotFound extends RuntimeException
{
    public function __construct(int $number)
    {
        parent::__construct("No task T{$number} in the file.");
    }
}
