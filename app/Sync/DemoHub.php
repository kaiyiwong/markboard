<?php

namespace App\Sync;

use Illuminate\Filesystem\Filesystem;

/**
 * The app's own copy of demo/, the hub it works on when MARKBOARD_HUB_PATH is unset, so editing
 * the demo never changes tracked files.
 */
final readonly class DemoHub
{
    public function __construct(
        private string $source,
        public string $path,
        private Filesystem $files = new Filesystem,
    ) {}

    /** Copies demo/ on first use; an existing copy keeps its edits. */
    public function ensure(): string
    {
        if (! is_dir($this->path)) {
            $this->copy();
        }

        return $this->path;
    }

    /** Replaces the copy, and every edit made to it, with a fresh one from demo/. */
    public function reset(): void
    {
        $this->files->deleteDirectory($this->path);
        $this->copy();
    }

    /**
     * Copies into a temp folder next to the copy's path, then renames it into place, so a request
     * arriving mid-copy never syncs half a hub. If another request got there first, its copy wins.
     */
    private function copy(): void
    {
        $temp = dirname($this->path).'/.'.basename($this->path).'.markboard-'.bin2hex(random_bytes(6)).'.tmp';
        $this->files->copyDirectory($this->source, $temp);

        if (! @rename($temp, $this->path)) {
            $this->files->deleteDirectory($temp);
        }
    }
}
