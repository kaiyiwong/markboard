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
    counts: Record<Exclude<SectionName, 'Done'>, number>;
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
