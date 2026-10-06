<?php

namespace App\Sync;

/**
 * The hub folder and the paths of the files in it that sync reads.
 */
final readonly class Hub
{
    public function __construct(
        public string $path,
    ) {}

    /** A folder without a projects.md is not a hub: every page shows "hub not found". */
    public function exists(): bool
    {
        return is_file($this->registryPath());
    }

    /** A demo-kind hub may use relative registry paths, and falls back to the bundled checker. */
    public function isDemoKind(): bool
    {
        return is_file($this->path.'/.markboard-demo');
    }

    public function registryPath(): string
    {
        return $this->path.'/projects.md';
    }

    public function prioritiesPath(): string
    {
        return $this->path.'/priorities.md';
    }
}
