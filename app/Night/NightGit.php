<?php

namespace App\Night;

use Closure;
use Symfony\Component\Process\Process;

/**
 * Git on one project's repo, for its night branches (`night/<task>`, built in a worktree beside the
 * project folder). safe.directory lets the Sail container act on a repo the host user owns.
 */
final readonly class NightGit
{
    private const int TIMEOUT_SECONDS = 60;

    /** The longest diff the page gets; anything longer is cut and says so. */
    public const int MAX_DIFF_BYTES = 400_000;

    public function __construct(public string $repo) {}

    public static function branch(string $task): string
    {
        return "night/{$task}";
    }

    /** Where the hub's runner puts a task's worktree: beside the project, named after it. */
    public function worktreePath(string $task): string
    {
        return dirname($this->repo).'/'.basename($this->repo).'-night-'.$task;
    }

    public function isRepo(): bool
    {
        return is_dir($this->repo) && $this->run(['rev-parse', '--git-dir'])->isSuccessful();
    }

    /** The branch's full commit hash, or null when there's no such branch. */
    public function head(string $branch): ?string
    {
        $process = $this->run(['rev-parse', '--verify', '--quiet', "refs/heads/{$branch}"]);

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }

    /** main, or master in an older repo; null if it has neither. */
    public function baseBranch(): ?string
    {
        foreach (['main', 'master'] as $branch) {
            if ($this->head($branch) !== null) {
                return $branch;
            }
        }

        return null;
    }

    /** @return list<array{sha: string, subject: string}> the branch's own commits, oldest first */
    public function commits(string $branch, string $base): array
    {
        $out = $this->run(['log', '--reverse', '--format=%h%x09%s', "{$base}..{$branch}"])->getOutput();
        $commits = [];
        foreach (array_filter(explode("\n", $out)) as $line) {
            [$sha, $subject] = array_pad(explode("\t", $line, 2), 2, '');
            $commits[] = ['sha' => $sha, 'subject' => $subject];
        }

        return $commits;
    }

    /**
     * What the branch changes since it left the base, one entry per file.
     *
     * @return array{files: list<array{path: string, added: int|null, removed: int|null, patch: string}>, truncated: bool}
     */
    public function diff(string $branch, string $base): array
    {
        $stats = [];
        foreach (array_filter(explode("\n", $this->run(['diff', '--numstat', "{$base}...{$branch}"])->getOutput())) as $line) {
            [$added, $removed, $path] = array_pad(explode("\t", $line, 3), 3, '');
            $stats[$path] = [is_numeric($added) ? (int) $added : null, is_numeric($removed) ? (int) $removed : null];
        }
        $patch = $this->run(['diff', '--no-color', "{$base}...{$branch}"])->getOutput();
        $truncated = strlen($patch) > self::MAX_DIFF_BYTES;
        if ($truncated) {
            $patch = substr($patch, 0, self::MAX_DIFF_BYTES);
        }
        $files = [];
        foreach (preg_split('/^(?=diff --git )/m', $patch, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $chunk) {
            $path = preg_match('#^diff --git a/.+? b/(.+)$#m', $chunk, $match) === 1 ? $match[1] : '';
            [$added, $removed] = $stats[$path] ?? [null, null];
            $files[] = ['path' => $path, 'added' => $added, 'removed' => $removed, 'patch' => rtrim($chunk, "\n")];
        }

        return ['files' => $files, 'truncated' => $truncated];
    }

    public function currentBranch(): string
    {
        return trim($this->run(['branch', '--show-current'])->getOutput());
    }

    /** @return list<string> files with uncommitted changes in the main checkout */
    public function uncommitted(): array
    {
        $out = $this->run(['status', '--porcelain'])->getOutput();

        return array_values(array_map(fn (string $line): string => substr($line, 3), array_filter(explode("\n", $out), fn (string $line): bool => strlen($line) > 3)));
    }

    /**
     * Merges the branch into the checked-out branch with a merge commit. Before the commit,
     * `$beforeCommit` may change files in the checkout; it returns a commit message and the files to
     * add, so they land in the merge commit itself. On a conflict, or a commit that fails (a hook),
     * the merge is aborted, so the checkout is as it was.
     *
     * @param  Closure(): array{message: ?string, add: list<string>}  $beforeCommit
     * @return array{ok: true, commit: string}|array{ok: false, conflicts: list<string>, message: string}
     */
    public function merge(string $branch, Closure $beforeCommit): array
    {
        $merge = $this->run(['merge', '--no-ff', '--no-commit', $branch]);
        if (! $merge->isSuccessful()) {
            $conflicts = array_values(array_filter(explode("\n", $this->run(['diff', '--name-only', '--diff-filter=U'])->getOutput())));
            $this->run(['merge', '--abort']);

            return ['ok' => false, 'conflicts' => $conflicts, 'message' => trim($merge->getErrorOutput()."\n".$merge->getOutput())];
        }
        ['message' => $message, 'add' => $add] = $beforeCommit();
        if ($add !== []) {
            $this->run(['add', '--', ...$add]);
        }
        $commit = $this->run($message === null ? ['commit', '--no-edit'] : ['commit', '-m', $message]);
        if (! $commit->isSuccessful()) {
            $this->run(['merge', '--abort']);

            return ['ok' => false, 'conflicts' => [], 'message' => trim($commit->getErrorOutput()."\n".$commit->getOutput())];
        }

        return ['ok' => true, 'commit' => trim($this->run(['rev-parse', '--short', 'HEAD'])->getOutput())];
    }

    /**
     * How far the branch is ahead of its remote: its upstream, or `origin/<branch>`. Null with no
     * remote branch to compare with. Read from the last fetch; nothing is fetched.
     *
     * @return array{remote: string, ahead: int}|null
     */
    public function unpushed(string $branch): ?array
    {
        $upstream = $this->run(['rev-parse', '--abbrev-ref', '--symbolic-full-name', "{$branch}@{upstream}"]);
        $remote = $upstream->isSuccessful() ? trim($upstream->getOutput()) : "origin/{$branch}";
        $count = $this->run(['rev-list', '--count', "refs/remotes/{$remote}..refs/heads/{$branch}"]);

        return $count->isSuccessful() ? ['remote' => $remote, 'ahead' => (int) trim($count->getOutput())] : null;
    }

    /** Takes the task's worktree and branch away; `force` also deletes a branch that wasn't merged. */
    public function removeBranch(string $task, bool $force): void
    {
        $worktree = $this->worktreePath($task);
        if (is_dir($worktree)) {
            $this->run(['worktree', 'remove', '--force', $worktree]);
        }
        $this->run(['worktree', 'prune']);
        $this->run(['branch', $force ? '-D' : '-d', self::branch($task)]);
    }

    /** @param  list<string>  $args */
    private function run(array $args): Process
    {
        $process = new Process(['git', '-c', 'safe.directory=*', '-C', $this->repo, ...$args], null, ['GIT_TERMINAL_PROMPT' => '0']);
        $process->setTimeout(self::TIMEOUT_SECONDS);
        $process->run();

        return $process;
    }
}
