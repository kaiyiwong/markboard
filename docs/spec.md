# Markboard: spec

Written 2026-10-06 from `docs/decisions.md` (numbers in brackets, such as [D2], point to it), then revised the same day after two spec reviews (`docs/spec-review.md`).

## What it is

A local web app over a folder of plain-markdown project files. It shows every project in priority order, one project's tasks, a job-search pipeline as a board and a daily brief, and lets the user edit tasks and pipeline rows in the browser.

The files are the source of truth. AI agents, scripts and editors keep changing them directly. Markboard copies them into MySQL so it can filter and search, and writes edits back to the files, changing only the lines that the edit is about.

## What it is not

- Not a store of its own. Nothing is typed into the database. The index tables can be dropped and rebuilt from the files at any time; the only other tables are history (`file_versions`, `conflicts`).
- Not multi-user. One person, on their own machine, on localhost, with no auth.
- Not an editor for the registry, priorities or the brief. Those are read-only here.
- Never creates, deletes or renames a file. It only rewrites existing TASKS.md and pipeline.md files.

## Configuration

| Variable | Meaning | Default |
|---|---|---|
| `MARKBOARD_HUB_PATH` | The hub folder | unset: a copy of the demo hub (see Demo hub) |
| `MARKBOARD_TIMEZONE` | The timezone that defines "today" for dates the app writes | the app timezone (UTC) |
| `MARKBOARD_PROJECTS_ROOT` | Host folder mounted into Sail at the same path; it must contain the hub and the project folders | unset: an empty placeholder folder is mounted (see Running) |

## The hub folder

| Path in the hub | What it is | Markboard |
|---|---|---|
| `projects.md` | The registry: one table row per project | reads |
| `priorities.md` | The category order used for ranking | reads |
| `TODAY.md` | Today's brief | reads |
| `briefs/YYYY-MM-DD.md` | Past briefs | reads |
| `scripts/check-tasks.py` | The TASKS.md format checker | runs before every TASKS.md write |

Each registered project's folder holds a `TASKS.md`, and may hold a `pipeline.md`. Those are the only files Markboard writes.

**Demo-kind hubs.** A hub that contains a `.markboard-demo` file is demo-kind: its registry may use relative paths, and when it has no `scripts/check-tasks.py`, the bundled checker is used. `demo/` ships with that file, so every copy of it (the app's demo copy, each test's temp copy) is demo-kind. Any other hub is a real hub.

**Hub missing.** If the configured folder doesn't exist or has no `projects.md`, every page shows "hub not found" with the path, and every write returns `409`.

### projects.md

```
| id | name | path | category | status | next milestone | docs |
|---|---|---|---|---|---|---|
| lantern | Lantern | /Users/sam/projects/lantern | product | active | Public beta | docs/spec.md |
```

- The table is the first line whose cells are exactly `id`, `name`, `path`, `category`, `status`, `next milestone`, `docs` (trimmed, in any order), the separator line under it, and the `|` lines that follow without a break. Cells are read by header name. Everything else in the file is ignored.
- A row is malformed, skipped and listed on the Projects page when: its cell count differs from the header's; `id` is empty or not kebab-case (`[a-z0-9]+(-[a-z0-9]+)*`); `category` isn't one of `own-site`, `client`, `job`, `product`, `game`, `gen-ai`, `personal`; `status` isn't one of `active`, `paused`, `done`; or `path` is empty, or relative in a real hub. A repeated `id` keeps the first row; later ones are listed as duplicates.
- A relative `path` (demo-kind hubs only) is resolved against the hub folder, so the demo works from any clone.
- A relative path is allowed only in a demo-kind hub; in a real hub it makes the row malformed.
- A row whose folder doesn't exist (or isn't mounted, in Sail) is shown with "folder not found" and has no tasks.

### priorities.md

Only its `Order:` line is read, for example `Order: own-site, client, job, product = game = gen-ai`. Categories joined by `=` rank equal. Categories not named, `personal` included, rank after all named ones. Equal categories keep registry order. With no `Order:` line, every category ranks equal.

### TASKS.md

```
# Lantern tasks

Free text is allowed here, before the first section.

## Up next
- [ ] T12 Export to CSV (due 2026-10-20)
  proof: the export opens in a spreadsheet with one row per task
- [ ] T15 Keyboard shortcuts (v2)

## In progress
- [ ] T11 Settings page (started 2026-10-01)
  note: waiting on the new icon set for the toggles

## Waiting on
- [ ] T09 Copy review (waiting Ana, since 2026-09-28)

## Done
- [x] T10 Sign-in flow (started 2026-09-22, done 2026-09-27, evidence a1b2c3d, from In progress)
- [x] T07 Offline mode (cancelled 2026-09-24)
```

The format is defined by `check-tasks.py`; this is a summary:

- Four sections in this order, all present: `## Up next`, `## In progress`, `## Waiting on`, `## Done`. Free text only before the first section.
- A task line is `- [ ] T<n> <title>`, optionally ending in metadata: the final parentheses, as comma-separated `key value` pairs, but only if every key is known. Otherwise the parentheses belong to the title, as in "(v2)" above.
- Known keys: `due`, `started`, `waiting`, `since`, `done`, `evidence`, `from`, `cancelled`. Dates are `YYYY-MM-DD`. Values contain no commas and can't be empty.
- In progress needs `started`. Waiting on needs `waiting` and `since`. Done needs `done`, or `cancelled` and no `done`. `[x]` appears only in Done, and every Done task is `[x]`.
- Indented lines (two spaces) after a task are only `proof: <text>` and `note: <text>`, with a non-space character after the colon and space. Blank lines between them don't end the task. A task may have several `proof:` lines; the last one counts.
- IDs are unique by number in the file (`T9` and `T09` are the same ID) and never reused. The next ID is the highest number plus 1, written without padding (`T16`).

### pipeline.md

```
# Pipeline

| company | role | stage | next action | date |
|---|---|---|---|---|
| Northwind | Senior Engineer | interviewing | prepare system design | 2026-10-14 |
```

Markboard owns this format's checks (no external checker exists for it):

- The table is the first line whose cells are exactly `company`, `role`, `stage`, `next action`, `date` (trimmed, in any order), then a separator line (cells of `-`, optionally with `:`), then data rows: the `|` lines that follow without a break. Everything else in the file is free text and is never touched. Cells are read by header name, so the column order is whatever the header says.
- A line is split on `|` after removing one leading and one trailing `|`; escaped pipes (`\|`) aren't supported.
- Format errors (the file is then read-only): no header line; no separator under it; a data row whose cell count differs from the header's; a `stage` not in the list below; a `date` that isn't empty or a real `YYYY-MM-DD` date; invalid UTF-8; a line break other than `\n` or `\r\n` (see the line model).
- `stage` is open (`applied`, `screening`, `interviewing`, `offer`) or closed (`accepted`, `rejected`, `withdrawn`, `closed`).
- A row is identified by its position among the data rows, starting at 1 [D9].

## Reading files: the line model

The parsers in `app/Markdown/` are plain PHP with no Laravel dependency. The same line model serves TASKS.md and pipeline.md.

**Lines.** A file is split into lines at the same boundaries the checker uses: Python opens the file with universal newlines and then calls `splitlines()`, so the boundaries are `\r\n`, `\r`, `\n`, `\v`, `\f`, `\x1c`, `\x1d`, `\x1e`, U+0085, U+2028 and U+2029. Line numbers therefore match the checker's. Each line keeps its own terminator; the last line's may be empty.

**Editable files.** A file is editable only if it is valid UTF-8, every terminator is `\n` or `\r\n`, and it has no format errors. One deliberate difference from the checker: Python's `\d` also matches non-ASCII digits, so it reads `T٣` as a task ID; the PHP parser accepts only `0-9`, so such a line is "text between sections" and the file is read-only, never written wrongly. Invalid UTF-8 and other terminators are Markboard's own file errors, reported next to the checker's format errors. A file that isn't editable is shown read-only, with its errors and line numbers [D6].

**Task blocks.** A block is a task line plus every following line up to the last `proof:` or `note:` line that belongs to it, which matches how the checker attaches indented lines: blank lines between a task and its indented lines are inside the block and move with it. Blank lines after the block's last line belong to no block and stay where they are. A block ends before the next task line, section heading or other non-blank, non-indented line.

**Parsed structure.** For each file: the preamble (lines before the first section heading), each section's heading line index and its blocks in order, and for each block its line range, ID as written, number, checkbox, title, ordered metadata pairs (a repeated key keeps every pair; the first value is used), proofs and notes in order. Parsing is best-effort: a file with errors still yields every task it can read.

**Writing.** Writing never re-renders the file. An edit is a list of line operations on the original lines: replace a block, remove a block, insert a block before a line index. Every line the edit doesn't name keeps its exact bytes, terminator included [D22]. New lines get the file's dominant terminator: the more common of `\n` and `\r\n`, with ties and one-line files going to `\n`. The file's final-newline state never changes. If the file's last line has no terminator, an operation first gives it the dominant terminator (in memory), works on the lines, and then removes the terminator from whatever line ends up last. So inserting after, removing, or moving the last block (an Undo or Reorder of the last task in Done, for example) can never join two lines.

**Rewriting a task line.** When an operation changes a task line, the line is rendered in a fixed form: `- [ ] T<id> <title>` or `- [x] ...`, with the ID as written, the title as parsed, and the metadata as ` (key value, key value)` with single spaces. A rewritten line keeps its own terminator. A task line the operation doesn't change keeps its original bytes.

**Every rendered line must read back as written.** After rendering, the line is parsed again; it must give exactly the intended checkbox, ID, title and metadata. If it doesn't (an existing title such as "Chase (waiting Bob)" would turn into metadata once the task's last key is removed by Undo or by Edit `due: null`), the operation is refused with `422` and a message to change the title first.

**Metadata.** "Set" a key: replace its value in place if present (every copy, if repeated, collapses to the first position), otherwise append it at the end. "Remove" a key: drop every copy. Removing the last key removes the parentheses.

## Editing tasks

Every edit names a project, a task (by number, except Add) and the hash of the file version the user saw. Each operation is one action class in `app/Actions/`. "Today" is the current date in `MARKBOARD_TIMEZONE`.

### Operations

| Operation | Allowed from | Result [D7, D8] |
|---|---|---|
| Add | (file) | New block `- [ ] T<next> <title>`, with `due` set if given, and a `proof:` line if given, inserted after the last block in Up next (right after the heading if Up next has no tasks) |
| Edit | any section | See "Edit fields" below |
| Move | Up next, In progress, Waiting on, to another of those three | Block to the top of the target section. Into In progress: set `started` (today) if missing. Into Waiting on: set `waiting` (required in the request) and `since` (today). Into Up next: no metadata change. Other keys are kept. |
| Reorder | any section | Block to a 0-based position among that section's tasks |
| Tick | Up next, In progress, Waiting on | Block to the top of Done. `[ ]` becomes `[x]`. Set `done` (today), set `evidence` if given (an existing one is kept otherwise), set `from <section>`, remove `cancelled`. |
| Cancel | Up next, In progress, Waiting on | Block to the top of Done. `[ ]` becomes `[x]`. Set `cancelled` (today), set `from <section>`, remove `done` and `evidence`. |
| Undo | Done, with `from` | Block to the top of its `from` section. `[x]` becomes `[ ]`. Remove `done`, `cancelled`, `evidence` and `from`. Then, into In progress: set `started` (today) if missing. Into Waiting on: set `waiting` from the request if the task has none (422 if neither), and `since` (today) if missing. |

"Top of a section" means directly after its heading line. Tasks are never deleted.

### Edit fields

PATCH is partial: a field left out is unchanged.

| Field | Value | Effect |
|---|---|---|
| `title` | string | Replaces the title |
| `due` | date or `null` | Sets or removes `due` |
| `proof` | string or `null` | Replaces the last `proof:` line in place and removes any other `proof:` lines; with no proof line, inserts one directly under the task line. `null` removes every `proof:` line. |
| `notes` | array of strings | Removes every `note:` line and inserts the new ones, in order, where the first note line was, or after the block's last line if there was none. `[]` removes all notes. |

### Validation (422)

Anything that passes validation must also pass the checker; the rules exist to guarantee that. The rules live in a plain PHP class, `app/Markdown/InputRules`, which the Form Requests call, so they are unit-tested in T2 without booting the app.

- **Every text value** (title, proof, notes, `waiting`, `evidence`): trimmed of every character Python's `str.isspace()` counts as whitespace (Unicode-aware, the same set the checker's `\S` excludes, including the no-break space); refused if it contains a line boundary from the line model or any other control character (U+0000 to U+001F, U+007F).
- **Lengths**: title at most 300 characters; proof, each note, `waiting` and `evidence` at most 500.
- **Title**: not empty; refused if it ends in a parenthesised group whose comma-separated parts all start with a known key (for example "Ship (due 2026-11-01)" or "Release (done)"), whether or not the task has other metadata, so a title can never turn into metadata.
- **Metadata values** (`due`, `waiting`, `evidence`): not empty; no `,`, `(` or `)`.
- **`due`**: a real date in `YYYY-MM-DD`.
- **Proof and each note**: not empty.
- **State** (checked by `TasksEditor`, not `InputRules`, because the editor can't build a file without them; it throws `EditRefused`, which the action classes turn into `422`): moving a task to the section it is in or to Done; moving into Waiting on without `waiting`; moving, ticking or cancelling a task in Done; undoing a task that isn't in Done or has no `from`; a reorder position below 0 or beyond the section's last task.

### Pipeline operations

| Operation | Result |
|---|---|
| Edit row `n` (PATCH, partial) | Fields: `company`, `role`, `stage`, `next_action`, `date` (`null` or `""` empties it). The row line is rewritten in the header's column order as `\| a \| b \| c \| d \| e \|`. No other line changes. |
| Add row | All five fields (date may be empty). A new row line after the last data row, or directly after the separator if the table has no rows. |

Validation: `company`, `role`, `next_action` not empty, at most 200 characters, trimmed like every text value, and containing no line boundary, control character or `|`; `stage` from the list; `date` empty or a real `YYYY-MM-DD` date. A file with no table is not editable (409).

## The write path

For every edit, in this order [D2, D3, D5]:

1. Resolve the file's real path (following symlinks); refuse anything that isn't a regular file (409). Take the file's lock: a Laravel cache lock named after the real path, held for at most 30 seconds (longer than the checker's 10-second timeout plus the write), waited for at most 3. If it can't be taken: `503` with `Retry-After: 1`.
2. Read the file and hash it (SHA-256 of the bytes). If it no longer exists: `409`. If the hash isn't the one in `If-Match`: stop, re-sync the file, record a conflict, respond `412` (see Conflicts).
3. If the file isn't editable (format or file errors, or no pipeline table): `409`. If the task number or row isn't in the file just read: `404`. Run the state checks from Validation: `422`.
4. Apply the operation to the lines.
5. Check the candidate text:
   - It must parse with no errors in the PHP parser.
   - TASKS.md only: the checker must pass it. The candidate is written to a scratch file under `storage/app/check/`, and `python3 <checker> <scratch file>` runs with a 10-second timeout. A pass is exit code 0 with empty stdout and empty stderr. Printed format errors mean a bug in Markboard: `500`, nothing written. Anything else (no python3, a crash, a non-zero exit, a timeout) means the checker couldn't give its verdict: `503`, nothing written. The scratch file is deleted either way.
   - The checker is `{hub}/scripts/check-tasks.py`. A demo-kind hub without one uses the bundled copy at `resources/checker/check-tasks.py`; a real hub without one gets `503` on every TASKS.md write.
6. Write the candidate to a temp file next to the real file, named `.<file name>.markboard-<random>.tmp`, with the original's permissions.
7. Hash the original again. If it changed since step 2, delete the temp file and take the `412` path.
8. Rename the temp file over the real path (atomic on the same filesystem). A symlink stays a symlink, because the real file is what gets replaced.
9. Re-sync the file, still holding the lock: the write path calls sync's per-file step directly, so it doesn't try to take the lock again (steps 2 and 7's re-syncs work the same way). Release the lock, and respond `200` with the result and the new `ETag`.

Any failure after step 6 deletes the temp file. Sync also deletes `.*.markboard-*.tmp` files older than one minute in the folders it reads, so a crash can't leave one behind for long.

Steps 7 and 8 leave a window of microseconds in which another process could save the file and lose its change. Other processes can't be made to take the lock, so this is accepted and stated rather than hidden.

## Conflicts

A conflict is a refused edit [D2, D4, D19]:

- Every `412` stores a conflict: the file path, the operation and its parameters, the hash the user had (`base_hash`) and the hash on disk (`disk_hash`). Two stale submits are two conflicts.
- The `412` body holds the conflict (id, and whether it can be applied), the current task or row (none for an Add), the current `etag`, and, when the base version is still stored, a line diff from it to the current version.
- **Can be applied** when the base version is still stored and the operation's lines are unchanged between base and current: for Edit, Tick, Cancel, Undo and Move, the task's block bytes and its section; for Reorder, every block in that section and their order; for a pipeline row edit, that row line at the same position; Add and pipeline Add are always applicable.
- **Apply** needs `If-Match` with the `etag` the conflict panel showed. It refuses with `409` a conflict that is resolved or superseded. It reads the file under the lock: if the disk hash isn't the `If-Match` hash, `412`: this conflict is superseded and a new one created that keeps the original `base_hash` (only its `disk_hash` is new), so applicability is always judged from the version the user first edited. Otherwise it re-checks "can be applied" between the base version and the version on disk now; if that fails, `409` and the conflict stays open (the user can only discard it). If it passes, the operation runs through the write path from step 3, and success resolves the conflict.
- **Discard** needs no `If-Match`, marks the conflict resolved and changes nothing.
- Unresolved conflicts are shown above the affected file's tasks on the Project page, and above the board on the Pipeline page.

## Sync

Sync copies files into the database [D13]:

1. For each source file (the registry, priorities, every registered TASKS.md and pipeline.md), compare its mtime and size with `source_files`. If both are unchanged and the stored mtime is more than 2 seconds older than the last sync of that file, skip it. (`filemtime` has one-second resolution; the 2-second rule catches two writes in the same second with the same size.)
2. Otherwise take the file's lock (the same lock as writes; if it's busy, skip the file this time, since the holder will sync it) and hash it. If the hash is unchanged, update mtime and size and skip.
3. Otherwise parse it and, in one transaction: update `source_files` (hash, mtime, size, errors), delete the file's rows and insert the freshly parsed ones. Store the version in `file_versions`. If the transaction fails, it rolls back, so the old rows and the old hash stay together; the failure is recorded in `source_files.sync_error` and shown on the Projects page, and the next sync tries again.
4. Projects no longer in the registry lose their rows. A registered project whose TASKS.md is missing or has format errors is "not migrated" [D6]: missing shows "no TASKS.md", errors show the count and the tasks read on a best-effort basis.

Pages read each file's `etag` and its rows in one database transaction, so the etag always describes the rows shown.

When sync runs:
- **On every web and API request**, through middleware, before the controller. With about 30 files this costs a few milliseconds.
- **While a page is open**, through Inertia's `usePoll`: a partial reload every 15 seconds and when the window regains focus [D12]. Polling pauses while an edit form is open or a drag is in progress.
- **Every minute**, by the scheduler (`markboard:sync` in `routes/console.php`). In Sail, `sail artisan schedule:work` runs it; it keeps search current when no page is open. First to cut if time is short [D1].
- **By hand**: `php artisan markboard:sync`. `--fresh` empties the index tables and rebuilds them from the files; `file_versions` and `conflicts` are kept.

## Data model

| Table | Columns |
|---|---|
| `projects` | `id` (the registry id, string primary key), `name`, `path`, `category`, `status`, `next_milestone`, `docs`, `registry_order`, `category_rank`, `folder_found` |
| (lengths) | Every column holding text from a file is `TEXT`, except `projects.id`, `category`, `status`, `section`, `stage`, `task_id`, `kind` and `hash`, which the parsers limit to short known values, and `hash`/`path_hash`, which are 64-character hex. Paths are `TEXT`; each path-keyed table also stores `path_hash` (SHA-256 of the path) and indexes that instead, because a unique index on a long path plus a hash exceeds InnoDB's 3072-byte key limit. |
| `source_files` | `id`, `project_id` (null for hub files), `kind` (`registry`, `priorities`, `tasks`, `pipeline`), `path`, `path_hash` (unique), `hash`, `mtime`, `size`, `editable`, `errors` (JSON: line and message), `sync_error`, `synced_at` |
| `tasks` | `id`, `project_id`, `source_file_id`, `task_id` (as written, `T09`), `number`, `section`, `position`, `checked`, `title` (text), `metadata` (JSON, ordered pairs), `due`, `started`, `since`, `done`, `cancelled` (nullable dates), `waiting`, `evidence`, `from_section` (nullable strings), `proof` (text, the last proof), `notes` (JSON), `notes_text` (text, notes joined by newlines), `line_start`, `line_end`; unique on (`project_id`, `number`) |
| `pipeline_rows` | `id`, `source_file_id`, `project_id`, `position`, `company`, `role`, `stage`, `next_action`, `date` (nullable date) |
| `file_versions` | `id`, `path`, `path_hash`, `hash`, `content`, `last_seen_at`; unique on (`path_hash`, `hash`) |
| `conflicts` | `id`, `path`, `path_hash` (indexed), `operation`, `parameters` (JSON), `base_hash`, `disk_hash`, `status` (`open`, `resolved`, `superseded`), timestamps |

- `projects`, `source_files`, `tasks` and `pipeline_rows` are the index. `file_versions` and `conflicts` are history, keyed by path so they survive `--fresh`.
- `file_versions`: seeing a hash again updates its `last_seen_at`. Each file keeps its 10 most recently seen versions, plus every version an open conflict refers to.
- The date and string columns copy values out of the metadata so they can be filtered. A value that isn't a valid date is stored as null in its date column (the raw value stays in `metadata`). A repeated key uses its first value. In a file with errors, a repeated task number indexes only its first task.
- Index ids never appear in URLs or the API [D13]: projects and tasks are addressed by registry id and task number. Conflicts are the exception: they exist only in the database, so the API addresses them by their id.
- **Search** [D14]: a `FULLTEXT` index on `tasks` (`title`, `proof`, `notes_text`). The query is split into words at every character that isn't a letter or digit, so no boolean-mode operator (`+ - * " ( ) ~ < > @`) can reach MySQL. On MySQL each word of 3 or more characters becomes `+word*` in boolean mode (every word must match, as a prefix); InnoDB also ignores its stopwords. On SQLite (local tests) each word must appear in one of the three columns (`LIKE`). The two differ for short words and stopwords, so feature tests search for a word of 6 or more letters that isn't a stopword (for example "spreadsheet").

## API

Versioned JSON under `/api/v1`. Every write except Discard needs `If-Match: "<hash>"`. Successful writes return the file's new `ETag` [D10].

| Method and path | Does |
|---|---|
| `POST /api/v1/projects/{project}/tasks` | Add (`title`, `due`?, `proof`?) |
| `PATCH /api/v1/projects/{project}/tasks/{task}` | Edit (see Edit fields) |
| `POST /api/v1/projects/{project}/tasks/{task}/move` | Move (`section`, and `waiting` into Waiting on) |
| `PUT /api/v1/projects/{project}/tasks/{task}/position` | Reorder (`position`) |
| `POST /api/v1/projects/{project}/tasks/{task}/tick` | Tick (`evidence`?) |
| `POST /api/v1/projects/{project}/tasks/{task}/cancel` | Cancel |
| `POST /api/v1/projects/{project}/tasks/{task}/undo` | Undo (`waiting`? when undoing into Waiting on) |
| `PATCH /api/v1/projects/{project}/pipeline/rows/{n}` | Edit a pipeline row |
| `POST /api/v1/projects/{project}/pipeline/rows` | Add a pipeline row |
| `POST /api/v1/conflicts/{conflict}/apply` | Apply a conflict's operation to the current version |
| `POST /api/v1/conflicts/{conflict}/discard` | Discard a conflict |

`{task}` is `T` followed by digits and is looked up by number, so `T9` finds `T09`. Pipeline routes sit under the project whose folder holds the `pipeline.md` (decisions round 2).

| Status | When |
|---|---|
| `200` | Written. Body: the task or row as an API Resource. Header: `ETag`. |
| `404` | Unknown project, task number (in the file as read at step 2), row or conflict |
| `409` | The file is missing, isn't a regular file, isn't editable, or (pipeline) has no table; the conflict is already resolved |
| `412` | `If-Match` doesn't match the file on disk (see Conflicts for the body) |
| `422` | Validation or state checks failed (Form Request format) |
| `428` | No `If-Match` header |
| `500` | The candidate failed the format check: a Markboard bug; nothing written |
| `503` | The lock is busy (`Retry-After: 1`), or the checker couldn't run or isn't there |

Requests are validated by Form Requests. Responses use API Resources. Errors use Laravel's JSON error format.

## Pages

Inertia renders these pages. Every page's props include the `etag` of each file it shows, read with its rows (see Sync).

| Component | Route | Shows [D16, D17, D18] |
|---|---|---|
| `Projects/Index` | `/` | Active projects in priority order, then paused, then done (collapsed); malformed and duplicate registry rows listed at the end. Filters by category and status. Each row: name, category, status, next milestone, Up next / In progress / Waiting on counts, an overdue or due-within-7-days badge, or "not migrated" (with "no TASKS.md" or the error count) or "folder not found". A search box finds tasks across projects; results link to the task. |
| `Projects/Show` | `/projects/{project}` and `/projects/{project}/tasks/{task}` | The tasks by section in file order, with metadata, proof and notes. The task route scrolls to and highlights that task. Open conflicts above the tasks. A file that isn't editable is shown read-only, with its errors and line numbers. |
| `Pipeline/Index` | `/pipeline` | One board per registered project that has a `pipeline.md`, each titled with its project: open stages as columns, closed stages collapsed. Open conflicts above each board. |
| `Brief/Show` | `/brief` and `/brief/{date}` | `TODAY.md` (or `briefs/{date}.md`), with past briefs listed below. `{date}` must match `\d{4}-\d{2}-\d{2}` and the file must exist (404 otherwise). With no `TODAY.md`, the page says there's no brief yet and still lists past ones. |

**Brief rendering** [D15]: `Str::markdown()` with `html_input` set to `escape` and `allow_unsafe_links` off, after `[project-id] T12` references are turned into links to `/projects/{project}/tasks/T12`. Raw HTML in a brief shows as text.

**Editing controls on `Projects/Show`:**
- Each open task has a checkbox (tick, with an optional evidence field), a menu with Edit, Move to (the other open sections; Waiting on asks who) and Cancel, and a drag handle for reorder.
- Each task in Done with `from` has an Undo button.
- An "Add task" form sits at the bottom of Up next.
- On the Pipeline page, dragging a card to another column changes its stage, clicking a card edits its fields, and each board has an "Add row" form.

**Edits from the page** [D11]: a typed `fetch` wrapper sends the request with `If-Match` and handles `412` by showing the conflict. The etag sent is the one captured when the edit started: when a form opened, a drag began, or a button was clicked, from the props on screen at that moment. Polling never changes the etag of an edit in progress. After the response, the page reloads its props; it never shows a change the file didn't take.

The UI uses the design system in `design-system/` with the choices in `DESIGN.md`.

## Demo hub

`demo/` holds a made-up hub [D21]:
- `projects.md` with relative paths and seven projects: an own site, a client project with an overdue and a due-soon task, a job project with `pipeline.md` (fictional companies in every open stage and one closed), a product with tasks in every section (a cancelled one in Done, and a blank line between a task and its proof), a paused game, a gen-AI project whose TASKS.md has format errors, and a project with no TASKS.md.
- `priorities.md`, `TODAY.md` and two dated briefs in `briefs/`.
- `.markboard-demo`, the marker that makes it demo-kind. No `scripts/` folder, so the bundled checker is used.

With `MARKBOARD_HUB_PATH` unset, the app copies `demo/` to `storage/app/demo-hub/` (ignored by git) on first use and works on the copy, so editing the demo never changes tracked files. `php artisan markboard:demo --reset` replaces the copy.

## Running

- Laravel Sail: the app and MySQL 8.4, every port bound to `127.0.0.1` [D20].
- `compose.yaml` mounts `${MARKBOARD_PROJECTS_ROOT:-./docker/no-projects}` read-write at `${MARKBOARD_PROJECTS_ROOT:-/mnt/no-projects}` in the app container, so with the variable set, the registry's absolute paths work unchanged; unset, the empty placeholder folder `docker/no-projects/` (tracked through its `.gitkeep`) is mounted and the app uses its demo copy. The hub must be inside the mounted folder; a registered folder outside it shows as "folder not found".
- Python 3 (in Sail's image) runs the checker.

## Testing

- **Unit (Pest, no app boot), against `tests/Fixtures/`**: small files for edge cases the demo shouldn't carry: CRLF, mixed `\n` and `\r\n`, a lone `\r` and the other Python line boundaries, no final newline (including an empty `## Done` as the last line), a preamble, a blank line between a task and its proof, several proof lines, a note before a proof, titles ending in "(v2)" and other non-metadata parentheses, a title that would turn into metadata if its last key were removed, padded IDs, repeated metadata keys, invalid dates, a file with errors. For pipeline.md: columns in a different order, an empty table, an empty date, CRLF, no final newline, free text around the table, and one fixture per pipeline format error.
  - For every fixture, parse then write returns identical bytes.
  - For every operation, the line diff touches only the expected lines, and the result passes the checker.
  - Every value rule in `InputRules` has a test; for each rule that guards the format, the test also shows that the refused input would otherwise produce a file the checker rejects. Length rules have plain boundary tests. State checks (wrong section, not in Done, position out of range) are unit-tested with `TasksEditor` in T2; T6 tests their `422` responses.
- **Parity**: the PHP parser reports the same format errors (line and message) as `check-tasks.py` on every fixture, and on every demo file once `demo/` exists (T7). Markboard's own file errors are compared separately. When `MARKBOARD_HUB_PATH` is set locally, the same check runs over every TASKS.md in that hub; CI skips it.
- **Feature (Pest), against a temp copy of `demo/` per test** (demo-kind, with `MARKBOARD_HUB_PATH` pointed at the copy): sync (a changed file re-synced, an unchanged one skipped, the 2-second rule, `--fresh` rebuilding the index and keeping history); each page's Inertia props; each API endpoint and every status code in the table; apply and discard.
- **Concurrency**: through a test hook that runs between steps 4 and 7, a test changes the file on disk and checks that the step 7 re-hash catches it: nothing is written and a conflict is recorded. (A change between steps 7 and 8 is the accepted window and isn't tested.)
- **Browser (T8, first to cut)**: a Pest browser test (Playwright) ticks a task on `Projects/Show` and checks the temp hub's TASKS.md. CI installs Chromium for it.
- CI runs the PHP tests on MySQL, and the type check and build for the front end.

## Out of scope

- Editing the registry, priorities or the brief
- Creating, deleting or renaming files
- Auth, multiple users and any hosted deployment
- A board view of project phases and an ideas view
- Pushing changes over WebSockets [D12]
- Starting agents or scripts from the app
