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

    /** Today's brief, or a past one by its date (YYYY-MM-DD). */
    public function briefPath(?string $date = null): string
    {
        return $date === null ? $this->path.'/TODAY.md' : $this->path."/briefs/{$date}.md";
    }

    /** The night run's record: work orders, reports, verdicts, status.tsv, costs.tsv (docs/spec.md, Night). */
    public function ordersPath(string $file = ''): string
    {
        return $this->path.'/orders'.($file === '' ? '' : '/'.$file);
    }

    /** Where the hub's runner puts every project's night worktrees: a hidden folder beside the hub. */
    public function nightWorktreesPath(): string
    {
        return dirname($this->path).'/.night';
    }

    /** Present while a night run is going. */
    public function nightLockPath(): string
    {
        return $this->path.'/.run/night.lock';
    }

    /**
     * The dates of the past briefs, newest first.
     *
     * @return list<string>
     */
    public function briefDates(): array
    {
        $dates = array_map(fn (string $path): string => basename($path, '.md'), glob($this->path.'/briefs/*.md') ?: []);
        $dates = array_values(array_filter($dates, fn (string $date): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1));
        rsort($dates);

        return $dates;
    }
}
