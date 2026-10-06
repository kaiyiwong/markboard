<?php

namespace App\Markdown;

use RuntimeException;

/**
 * No data row at this position in the pipeline table.
 */
final class RowNotFound extends RuntimeException
{
    public function __construct(int $position)
    {
        parent::__construct("No row {$position} in the pipeline table.");
    }
}
