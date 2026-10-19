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
24. **AI assistance is stated openly.** Commits keep their `Co-Authored-By` line (replaced by D31), and the README says the app was built with Claude Code under a spec, review and verify workflow, with the design set and every change reviewed by the author.

## Round 2, 2026-10-06 (first spec review)

The first spec review (`docs/spec-review.md`, 31 issues) found four places where the spec had to go beyond or change a round 1 decision. Everything else it raised is a build detail, settled in `docs/spec.md`.

25. **Pipeline routes sit under the project** (`/api/v1/projects/{project}/pipeline/rows/{n}`), refining [D10]. A `pipeline.md` lives in a project folder, and any registered project may have one; the Pipeline page shows one board per such project.
26. **Cancel also records `from`, and Undo also removes `cancelled`**, extending [D8], so a cancelled task can be undone like a ticked one. Tick removes `cancelled` and Cancel removes `done` and `evidence`, so a task never carries both a done and a cancelled date.
27. **Two fixture sets**, refining [D21]: feature tests use a temp copy of `demo/`; parser unit tests also use small files in `tests/Fixtures/` for edge cases (mixed line endings, no final newline, odd line breaks) that don't belong in a demo.
28. **The scheduled sync stays in T4** (every minute, `schedule:work` in Sail), as the first thing to cut if time is short [D1].
29. **Demo-kind hubs**, refining [D5] and [D20]: a hub containing a `.markboard-demo` file may use relative registry paths and falls back to the bundled checker. `demo/` and every copy of it carry the file; a real hub never does, so a real hub without a checker still refuses writes.
30. **Conflicts are addressed by their database id**, an exception to [D13]: conflicts exist only in the database (they record refused edits, not file content), so there is no file-based key to use. Index rows (projects, tasks, pipeline rows) still never expose ids.

## Round 3, 2026-10-07 (starting T4)

31. **Commits carry no Claude attribution line**, replacing the commit part of [D24]. Kai's global rule for every project forbids it (deploy hosts reject such commits). The README still states the AI assistance, as D24 describes; commits before T4 keep theirs.
32. **`demo/` is built in T4**, refining [D21]: T4's sync tests and T5's page tests both run on a temp copy of it. T7 keeps the README, the app's demo copy with `markboard:demo --reset`, and the parity run over the demo files.

## Round 4, 2026-10-13 (the Night page, T15)

The hub now runs agents overnight. A chief of staff picks tasks and writes work orders, builders build them on `night/<task>` branches, and verifiers check them. The record lands in the hub's `orders/` folder. Kai: "we should make it as easy as it is for me to see what happen, review and update, that's the whole point of building it."

33. **Markboard shows the night run and lets Kai act on it.** This widens "What it is not" and "Out of scope" in a few named places:
    - Markboard writes an answer section into a work order and appends a row to `orders/status.tsv`.
    - It creates and deletes `orders/STOP` to skip a night.
    - It runs git in a registered project's repo, on `night/*` branches only: merge, or remove the worktree and branch.
    - It still starts no agent or script, and still never creates, deletes or renames a TASKS.md or pipeline.md.
34. **Night data is read from the files on each request, not indexed,** like the brief. It's a few small files that change once a night, and there's nothing in it to search.
35. **A git action is guarded by the branch head the page showed,** sent as `head`, instead of `If-Match`. Git state isn't one file's hash. If the branch has moved since the page showed it, the action is refused with `412`, and nothing changes.
36. **Merge needs a clean main checkout and never resolves anything.** If the project's main checkout has uncommitted changes, isn't on `main`, or the merge conflicts, Markboard aborts and refuses with `409`. It names the files and shows the command to run by hand. It never stashes, resets or pushes.

## Round 5, 2026-10-13 (the Night page as a daily tool)

Kai found the first Night page hard to act on: every review card fully open, long sentences everywhere, the actions at the bottom. He drew mocks of short, scannable cards: a headline, short check names, term pairs to tick, questions with choices, quick closes.

37. **The agents write the short text; Markboard never makes it up.** The verdict, the work order's `## MISSING` and the triage note get optional structure: a headline, a short name and a few words of evidence per check, `## Confirm:` blocks of rows to check by eye, choices and a "why" per question, short names, runs and reasons in triage, and `## Quick closes`. Markboard reads it and falls back to the long text when it's missing. It doesn't shorten text itself, since that would need a model call, and the app makes none.
38. **Close ticks, Keep notes.** A quick close's Close ticks the task through the existing write path (the project's TASKS.md etag, the check script, the round trip), so it lands in Done like a tick on the Project page. Keep writes one `kept: <date>` line under the item in the triage note, through the same lock and double hash check as an answer, so the next triage stops suggesting it. A quick close leaves the page once its task is in Done or it's kept.
39. **"Looks right" ticks stay in the browser.** They're a reviewer's notes while deciding, not hub data, so they go in local storage keyed by the branch head (a branch that moves starts unticked), and Merge doesn't depend on them.
40. **Merge ticks the task and leaves the push to Kai.** Kai asked whether Merge was the right step, and whether it should work like poteto's, whose agents push their own branches and open PRs. A pushed branch of a client project builds a preview on that client's production database, so night branches stay local and pushing `main` stays Kai's step. Two gaps closed instead:
    - **The tick goes in the merge commit.** Merge runs `git merge --no-ff --no-commit`, ticks the task through the write path (with the branch head as evidence), adds TASKS.md, and commits as `done <task>: merge night/<task>`. TASKS.md then never sits uncommitted in the main checkout, where it would block the next merge. Only a night task that is a whole TASKS.md task (`T<n>`) is ticked. A slice, a task the file doesn't have, or a file that can't be edited is merged without a tick, and the status row says why. A commit that fails (a hook) aborts the merge and puts the tick back.
    - **What isn't pushed shows on the page.** Each project the night merged into whose `main` is ahead of its remote (its upstream, or `origin/main`, as of the last fetch) is listed with its `git push` command, and counts as waiting on Kai. Markboard never fetches or pushes.
