<?php

namespace App\Sync;

/**
 * The kinds of file sync reads. Only tasks and pipeline files are ever written.
 */
enum SourceKind: string
{
    case Registry = 'registry';
    case Priorities = 'priorities';
    case Tasks = 'tasks';
    case Pipeline = 'pipeline';
}
