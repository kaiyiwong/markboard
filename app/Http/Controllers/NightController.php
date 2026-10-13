<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Night\Costs;
use App\Night\NightGit;
use App\Night\Queue;
use App\Night\StatusLog;
use App\Night\StatusRow;
use App\Night\Verdict;
use App\Night\WorkOrder;
use App\Sync\Hub;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The hub's night run (docs/spec.md, Night): what the chief of staff picked, what the builders made,
 * what the verifier said, and what needs Kai. Read from the hub's orders/ folder and the projects'
 * repos on each request, not indexed [D34].
 */
class NightController extends Controller
{
    /** Outcomes the page groups tasks by, in the order a reviewer needs them. */
    private const array GROUPS = [
        'ready' => ['ready'],
        'blocked' => ['blocked'],
        'stuck' => ['stuck', 'failed'],
        'skipped' => ['skipped'],
        'done' => ['merged', 'discarded'],
    ];

    public function show(Hub $hub, ?string $date = null): Response
    {
        $log = StatusLog::parse(self::read($hub->ordersPath('status.tsv')) ?? '');
        $nights = $log->nights();
        abort_if($date !== null && ! in_array($date, $nights, true), 404);
        $night = $date ?? ($nights[0] ?? null);

        /** @var array<string, Project> $projects */
        $projects = Project::all()->keyBy('id')->all();
        $rows = $night === null ? [] : $log->night($night);
        $groups = [];
        foreach (self::GROUPS as $group => $outcomes) {
            $groups[$group] = array_values(array_map(
                fn (StatusRow $row): array => self::task($hub, $row, $projects, $group === 'ready'),
                array_filter($rows, fn (StatusRow $row): bool => in_array($row->outcome, $outcomes, true)),
            ));
        }

        return Inertia::render('Night/Show', [
            'date' => $date,
            'night' => $night,
            'nights' => $nights,
            'running' => is_file($hub->nightLockPath()),
            'stopped' => is_file($hub->ordersPath('STOP')),
            'usage' => $night === null ? 0.0 : Costs::forNight(self::read($hub->ordersPath('costs.tsv')) ?? '', $night),
            'tasks' => $groups,
            'questions' => self::questions($hub, $projects),
            'triage' => $night === null ? null : self::markdown(self::read($hub->ordersPath("triage-{$night}.md"))),
            'queue' => self::queue($hub, $log, $projects),
        ]);
    }

    /**
     * One task of the night. A ready one also gets its verdict, report and branch, for review.
     *
     * @param  array<string, Project>  $projects
     * @return array<string, mixed>
     */
    private static function task(Hub $hub, StatusRow $row, array $projects, bool $review): array
    {
        $order = self::read($hub->ordersPath("{$row->project}/{$row->task}.md"));
        $task = [
            'project' => $row->project,
            'project_name' => $projects[$row->project]->name ?? $row->project,
            'task' => $row->task,
            'title' => $order === null ? null : WorkOrder::parse($order)->title,
            'outcome' => $row->outcome,
            'time' => $row->time,
            'commit' => $row->commit,
            'reason' => $row->reason,
            'rounds' => $row->rounds(),
        ];
        if (! $review) {
            return $task;
        }
        $verdict = self::read($hub->ordersPath("{$row->project}/{$row->task}.verdict.md"));
        $parsed = $verdict === null ? null : Verdict::parse($verdict);

        return $task + [
            'verdict' => $parsed === null ? null : [
                'result' => $parsed->result,
                'commit' => $parsed->commit,
                'summary' => $parsed->summary,
                'items' => $parsed->items,
                'scope_problems' => $parsed->scopeProblems,
                'look_at' => $parsed->lookAt,
            ],
            'report' => self::markdown(self::read($hub->ordersPath("{$row->project}/{$row->task}.report.md"))),
            'branch' => self::branch($projects[$row->project] ?? null, $row->task),
        ];
    }

    /**
     * The night branch as the reviewer sees it: its head (sent back by Merge and Discard), commits
     * and diff; or why it can't be shown.
     *
     * @return array<string, mixed>
     */
    private static function branch(?Project $project, string $task): array
    {
        $name = NightGit::branch($task);
        if ($project === null || ! $project->folder_found) {
            return ['name' => $name, 'state' => 'no-project'];
        }
        $git = new NightGit($project->path);
        if (! $git->isRepo()) {
            return ['name' => $name, 'state' => 'no-repo'];
        }
        $head = $git->head($name);
        $base = $git->baseBranch();
        if ($head === null || $base === null) {
            return ['name' => $name, 'state' => 'gone'];
        }

        return [
            'name' => $name,
            'state' => 'present',
            'head' => $head,
            'base' => $base,
            'worktree' => $git->worktreePath($task),
            'commits' => $git->commits($name, $base),
            ...$git->diff($name, $base),
        ];
    }

    /**
     * Every work order that waits on Kai, with its questions, any answers so far, and the etag an
     * answer sends back.
     *
     * @param  array<string, Project>  $projects
     * @return list<array<string, mixed>>
     */
    private static function questions(Hub $hub, array $projects): array
    {
        $questions = [];
        foreach (glob($hub->ordersPath('*/*.md')) ?: [] as $path) {
            if (substr_count(basename($path), '.') !== 1) {
                continue; // .report.md, .verdict.md, .runner.md
            }
            $text = (string) file_get_contents($path);
            $order = WorkOrder::parse($text);
            if (! $order->needsKai()) {
                continue;
            }
            $project = basename(dirname($path));
            $questions[] = [
                'project' => $project,
                'project_name' => $projects[$project]->name ?? $project,
                'task' => basename($path, '.md'),
                'title' => $order->title,
                'etag' => hash('sha256', $text),
                'missing' => self::markdown($order->sections['MISSING'] ?? null),
                'answers' => $order->sections[WorkOrder::ANSWERS] ?? null,
            ];
        }

        return $questions;
    }

    /**
     * Tonight's queue: what the next run will build, with what each line's task already did.
     *
     * @param  array<string, Project>  $projects
     * @return list<array<string, mixed>>
     */
    private static function queue(Hub $hub, StatusLog $log, array $projects): array
    {
        return array_map(fn (array $item): array => $item + [
            'project_name' => $projects[$item['project']]->name ?? $item['project'],
            'last' => $log->latest($item['project'], $item['task'])?->outcome,
        ], Queue::parse(self::read($hub->ordersPath('queue.txt')) ?? ''));
    }

    private static function read(string $path): ?string
    {
        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    /** Raw HTML shows as text and unsafe links are dropped, as in the brief [D15]. */
    private static function markdown(?string $text): ?string
    {
        return $text === null || trim($text) === '' ? null : Str::markdown($text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }
}
