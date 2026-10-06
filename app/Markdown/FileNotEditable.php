<?php

namespace App\Markdown;

use RuntimeException;

/**
 * The file has format or file errors, so it is read-only.
 */
final class FileNotEditable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The file has format errors, so it is read-only.');
    }
}
