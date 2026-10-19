// Shapes of the props the server sends each page (see app/Http/Resources and the page controllers).

export type SectionName = 'Up next' | 'In progress' | 'Waiting on' | 'Done';

export type Stage = 'applied' | 'screening' | 'interviewing' | 'offer' | 'accepted' | 'rejected' | 'withdrawn' | 'closed';

/** A format error, at its 1-based line (0 for the file as a whole). */
export interface FormatError {
    line: number;
    message: string;
}

/** Props every page gets (HandleInertiaRequests::share). */
export interface SharedProps {
    hub: { found: boolean; path: string };
    /** Today in MARKBOARD_TIMEZONE, as YYYY-MM-DD. */
    today: string;
    [key: string]: unknown;
}

/** A file a page shows. Its etag is what an edit sends back as If-Match. */
export interface SourceFile {
    path: string;
    etag: string | null;
    editable: boolean;
    errors: FormatError[];
    sync_error: string | null;
}

export interface Project {
    id: string;
    name: string;
    category: string;
    status: 'active' | 'paused' | 'done';
    next_milestone: string;
    docs: string;
    folder_found: boolean;
}

/** A project on the Projects page, with its counts and state. */
export interface ProjectSummary extends Project {
    tasks_file: 'ok' | 'errors' | 'missing';
    error_count: number;
    counts: Record<SectionName, number>;
    due: 'overdue' | 'soon' | null;
    sync_error: string | null;
}

export interface Task {
    task_id: string;
    number: number;
    section: SectionName;
    position: number;
    checked: boolean;
    title: string;
    /** The metadata pairs as written, in file order. */
    metadata: [string, string][];
    due: string | null;
    overdue: boolean;
    from_section: SectionName | null;
    proof: string | null;
    notes: string[];
    line_start: number;
    line_end: number;
}

export interface SearchResult {
    project_id: string;
    project_name: string;
    task_id: string;
    title: string;
    section: SectionName;
}

export interface PipelineRow {
    position: number;
    company: string;
    role: string;
    /** Null when the file's stage isn't one Markboard knows (the file is then read-only). */
    stage: Stage | null;
    next_action: string;
    date: string | null;
}

export interface Board {
    project: { id: string; name: string };
    file: SourceFile;
    rows: PipelineRow[];
    conflicts: Conflict[];
}

/** One line of a conflict's diff, with its 1-based number in the old version, the new one, or both. */
export interface DiffLine {
    op: 'same' | 'removed' | 'added';
    old: number | null;
    new: number | null;
    text: string;
}

/** An edit refused because the file changed on disk (a 412), as the conflict panel shows it. */
export interface Conflict {
    id: number;
    operation: string;
    summary: string;
    /** The file's etag now: Apply sends it as If-Match. */
    etag: string | null;
    applicable: boolean;
    /** The task or row as it is on disk now; null for an Add, or if it's gone. */
    current: Task | PipelineRow | null;
    /** Null when the version the user edited is no longer stored. */
    diff: DiffLine[] | null;
}

// The night run (docs/spec.md, Night; NightController).

export type NightOutcome = 'ready' | 'blocked' | 'failed' | 'stuck' | 'skipped' | 'merged' | 'discarded';

/** One task of a night: its latest status row, with its work order's title. */
export interface NightTask {
    project: string;
    project_name: string;
    task: string;
    /** The work order's `# ` title; null when the order is gone. */
    title: string | null;
    outcome: NightOutcome;
    /** YYYY-MM-DD HH:MM, the hub's local time. */
    time: string;
    commit: string;
    reason: string;
    rounds: number;
}

export type NightResult = 'PASS' | 'FAIL' | 'INCONCLUSIVE';

/** A `## Confirm:` block: things the verifier asks a human to check by eye, as rows of cells. */
export interface NightConfirm {
    title: string;
    /** Column headings; empty when the block has none. */
    columns: string[];
    groups: { label: string; rows: string[][] }[];
    note: string;
}

export interface NightVerdict {
    result: NightResult;
    commit: string;
    /** One line saying what changed; empty when the verifier didn't write one. */
    headline: string;
    summary: string;
    /** `short` and `says` are the check and its evidence in a few words; empty when not written. */
    items: { result: NightResult; item: string; evidence: string; short: string; says: string }[];
    scope_problems: string[];
    look_at: string[];
    confirm: NightConfirm[];
}

export interface NightFile {
    path: string;
    /** Null for a binary file. */
    added: number | null;
    removed: number | null;
    patch: string;
}

/** The night branch, or why it can't be shown (gone: merged or discarded; no-repo: the project isn't a git repo). */
export type NightBranch =
    | { name: string; state: 'gone' | 'no-repo' | 'no-project' }
    | {
          name: string;
          state: 'present';
          /** Merge and Discard send it back, so a branch that moved since is refused. */
          head: string;
          base: string;
          worktree: string;
          commits: { sha: string; subject: string }[];
          files: NightFile[];
          truncated: boolean;
      };

/** A project the night merged into whose main is ahead of its remote. Markboard never pushes. */
export interface NightUnpushed {
    project: string;
    project_name: string;
    base: string;
    /** The remote branch it's compared with, as of the last fetch: its upstream, or origin/<base>. */
    remote: string;
    ahead: number;
    command: string;
}

/** A task the night run left ready, with all a review needs. */
export interface NightReview extends NightTask {
    verdict: NightVerdict | null;
    /** The builder's report, rendered on the server. */
    report: string | null;
    branch: NightBranch;
}

/** A work order that waits on Kai. */
export interface NightQuestion {
    project: string;
    project_name: string;
    task: string;
    title: string | null;
    /** The order file's etag: an answer sends it as If-Match. */
    etag: string;
    /** The `## MISSING` questions, rendered on the server. */
    missing: string | null;
    /** Earlier answers, as written. */
    answers: string | null;
    /** How many questions `## MISSING` asks. */
    count: number;
    /** A short title and a line of context; empty when not written. */
    headline: string;
    context: string;
    /** Each question with its choices; null when the order gives none (`missing` shows as written). */
    asks: { ask: string; choices: string[]; why: string }[] | null;
}

/** A bullet of the triage note: the tasks it names, its text, and its indented `key: value` lines. */
export interface TriageItem {
    project: string;
    tasks: string[];
    text: string;
    fields: Record<string, string>;
}

/** A task the triage suggests closing. `etag` is its TASKS.md's, for the tick; null when it can't be edited. */
export interface QuickClose {
    project: string;
    project_name: string;
    task: string;
    short: string;
    why: string;
    etag: string | null;
}

/** The triage note's sections, when the chief of staff wrote their structure. */
export interface TriageParts {
    picked: TriageItem[];
    carried: TriageItem[];
    sent_back: TriageItem[];
    passed_over: TriageItem[];
    quick_closes: QuickClose[];
    seen: TriageItem[];
    held: TriageItem[];
}

/** How many of a night's tasks ended each way. */
export type NightTally = Record<'ready' | 'blocked' | 'stuck' | 'skipped' | 'done', number>;

export interface NightQueueItem {
    project: string;
    project_name: string;
    task: string;
    model: string;
    effort: string;
    /** The task's latest outcome, if it has run. */
    last: NightOutcome | null;
}
