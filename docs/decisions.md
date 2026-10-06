# Markboard: decisions

## Round 1, 2026-10-06 (design grilling before the spec)

Settled in one design review before any feature code, one question at a time, each with a recommended answer that was accepted or changed. The spec (docs/spec.md) is written from these.

### Scope

1. **First release: T1 to T6, a short README (part of T7) and T9.** The spec, the TASKS.md parser and writer, the pipeline, sync, the read views and conflict-safe edits, then the repo goes public. Cut first if time runs short: the browser end-to-end test (T8), then the scheduled sync. The parser and the conflict-safe API carry the design; a polished but shallow app shows less.

### Writing to files someone else also edits

2. **A conflict is checked against the whole file.** Each page carries the hash of every file it shows. An edit sends that hash as `If-Match`; if the file changed on disk at all, nothing is written. The conflict panel then offers "apply to the new version", but only when the edited task's own lines are unchanged between the two versions, and only as an explicit click. Simple to reason about, standard HTTP, and it never writes over something the user hasn't seen. A per-task check would let more edits through but would rest on the app's own diff logic.
3. **The write itself.** A lock stops two app requests writing the same file at once. The new text goes to a temp file in the same folder with the original's permissions; the original is re-hashed immediately before an atomic rename over it. Other processes (agents, scripts, editors) can't be locked out, so the design makes the remaining window as small as possible and detects conflicts instead of claiming to prevent them.
4. **Old versions are kept so a conflict can be explained.** A `file_versions` table stores each version's content by hash: the last 10 per file, plus any version an open conflict refers to. If the version a page was built from is gone, the conflict shows the current file with no "apply" button.
5. **The hub's own format checker has the final say.** The PHP parser gives the app its structure; before every write, the new text must also pass `check-tasks.py` from the configured hub (the demo uses a copy bundled in the repo). Tests keep the PHP parser in step with it. If the format evolves in the hub, the app refuses what the hub would reject.
6. **Files with format errors are read-only.** The dashboard marks them "not migrated" with the error count; the project page lists the tasks it could read on a best-effort basis, plus the errors with line numbers. They are never edited.

### Operations

7. **Where tasks land.** Every move goes to the top of the target section, the same as a tick (top of Done) and an undo (top of its `from` section). A new task goes to the bottom of Up next, because order in the file is priority. Reordering within a section is by drag.
8. **What each operation sets.** Add: title, optional due date and proof. Edit: title, due date, proof and notes. Tick: adds `done` and `from`, with `evidence` optional. Undo: removes those three. Move to In progress adds `started`; move to Waiting on asks who and adds `since`. Cancel: to Done with `cancelled`. The user never types `started`, `since`, `done`, `from` or `cancelled`; the operations set them, so they can't be wrong.
9. **Pipeline rows are addressed by position plus the file hash.** Rows have no IDs. `If-Match` guarantees row 3 is the row the user saw, so position is safe without adding an ID column to the file format.

### The API and the page

10. **An action endpoint per operation.** `POST .../tasks` (add), `PATCH .../tasks/{task}` (edit), `POST .../tasks/{task}/tick`, `/undo`, `/move`, `/cancel`, `PUT .../tasks/{task}/position`; `PATCH /api/v1/pipeline/rows/{n}` and `POST /api/v1/pipeline/rows`. Responses: `200` with the updated resource and the file's new `ETag`; `428` without `If-Match`; `412` when stale, with the current task, `ETag` and diff; `409` when the file has format errors; `422` for validation. Endpoints mirror what happens to the file, which is easier to reason about than one overloaded `PATCH`.
11. **No optimistic updates.** A typed `fetch` wrapper sends `If-Match` and handles `412`; on success Inertia reloads that page's props. Locally that takes tens of milliseconds, and the screen never shows a change the file didn't take.
12. **Open pages poll.** Inertia's `usePoll` does a partial reload every 15 seconds and when the window regains focus; each request runs the cheap mtime-and-size check. Pushing changes over WebSockets (Laravel Reverb plus a file watcher) is possible later, at the cost of two more moving parts.

### Data

13. **Sync replaces, never merges.** When a file's hash changes, its rows are deleted and re-inserted from the fresh parse in one transaction. Rows are disposable; the API and URLs use the project id and task ID (`/projects/{project}/tasks/T12`), never database ids. Projects removed from the registry lose their rows on the next sync; nothing is ever deleted from a file.
14. **Search uses a MySQL `FULLTEXT` index** on task title, notes and proof, through an Eloquent scope. At a few hundred rows `LIKE` would do; the index is there to show the design, with the write cost noted. Tests on SQLite fall back to `LIKE`; CI on MySQL covers the index.
15. **The brief is rendered on the server** with `Str::markdown()` (league/commonmark, already in Laravel), with `[project] T12` references turned into links first.

### Views

16. **Projects page.** Active projects in priority order (the registry's category order, then row order), then paused, then done (collapsed); filters by category and status. Each row: name, category, status, next milestone, Up next, In progress and Waiting on counts, an overdue or due-soon badge, and the "not migrated" state. A search box finds tasks across all projects. It does not read the daily sweeper's internal output.
17. **Pipeline board.** Open stages are columns, closed stages are collapsed; dragging a card changes its stage, and clicking it edits the next action and date.
18. **Brief page.** Today's brief, with past briefs listed below.
19. **The conflict panel** sits above the affected file's tasks on the project page, with the diff and Apply or Discard.

### Running it

20. **The app runs in Sail** with the projects folder mounted read-write at the same path inside the container, so the registry's absolute paths work unchanged. Every port binds to 127.0.0.1. `MARKBOARD_HUB_PATH` points at the hub; unset, the app uses `demo/`.
21. **The demo hub is made up.** About six fictional projects across the categories (one with format errors, one paused), a pipeline with fictional companies, and a brief. Tests use the same files as fixtures; nothing is copied from a real hub. A parity test against a real hub runs only when `MARKBOARD_HUB_PATH` is set, and CI skips it.
22. **Files keep their details.** Line endings, the final newline and any free text before the first section survive every edit; round-trip tests cover each.

### Process

23. **The GitHub repo exists from the start, private,** and goes public at T9, so CI runs on every push and each task has a pull request.
24. **AI assistance is stated openly.** Commits keep their `Co-Authored-By` line, and the README says the app was built with Claude Code under a spec, review and verify workflow, with the design set and every change reviewed by the author.

## Round 2, 2026-10-06 (first spec review)

The first spec review (`docs/spec-review.md`, 31 issues) found four places where the spec had to go beyond or change a round 1 decision. Everything else it raised is a build detail, settled in `docs/spec.md`.

25. **Pipeline routes sit under the project** (`/api/v1/projects/{project}/pipeline/rows/{n}`), refining [D10]. A `pipeline.md` lives in a project folder, and any registered project may have one; the Pipeline page shows one board per such project.
26. **Cancel also records `from`, and Undo also removes `cancelled`**, extending [D8], so a cancelled task can be undone like a ticked one. Tick removes `cancelled` and Cancel removes `done` and `evidence`, so a task never carries both a done and a cancelled date.
27. **Two fixture sets**, refining [D21]: feature tests use a temp copy of `demo/`; parser unit tests also use small files in `tests/Fixtures/` for edge cases (mixed line endings, no final newline, odd line breaks) that don't belong in a demo.
28. **The scheduled sync stays in T4** (every minute, `schedule:work` in Sail), as the first thing to cut if time is short [D1].
29. **Demo-kind hubs**, refining [D5] and [D20]: a hub containing a `.markboard-demo` file may use relative registry paths and falls back to the bundled checker. `demo/` and every copy of it carry the file; a real hub never does, so a real hub without a checker still refuses writes.
30. **Conflicts are addressed by their database id**, an exception to [D13]: conflicts exist only in the database (they record refused edits, not file content), so there is no file-based key to use. Index rows (projects, tasks, pipeline rows) still never expose ids.
