<?php

namespace App\Markdown;

/**
 * The four sections of a TASKS.md, in file order. The values are the headings' words.
 */
enum Section: string
{
    case UpNext = 'Up next';
    case InProgress = 'In progress';
    case WaitingOn = 'Waiting on';
    case Done = 'Done';

    public function isOpen(): bool
    {
        return $this !== self::Done;
    }
}
