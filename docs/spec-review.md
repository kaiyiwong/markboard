ANSWERED: the 5 issues open after round 3 (and the 3 leftovers) are answered in docs/spec.md; see "Answers" at the end.

Second review of `docs/spec.md`, after its revision. Checked against `docs/decisions.md` [D1]..[D28], the TASKS.md checker (read from source, not memory), the tick and undo rules the spec extends per [D26], `TASKS.md` T2 to T9, and the repo (`compose.yaml`, `config/`, `phpunit.xml`, `storage/app/.gitignore`, CI). MAJOR means a likely wrong build, or a path that can corrupt or overwrite a user's file. MINOR means a builder would have to guess, or a proof that can't be checked as written.

The count covers 5 earlier issues still open (3, 4, 5, 9, 30) and 8 new ones (N1 to N8).

---

## The 31 earlier issues

| # | Topic | State | Why |
|---|---|---|---|
| 1 | Blank lines between a task and its proof | SETTLED | "Task blocks" runs a block down to its last `proof:`/`note:` line, matching the checker; a fixture is named |
| 2 | How the checker runs, what counts as a pass | SETTLED | Scratch file, exit 0 plus empty stdout and stderr, 10 s timeout, 503 when it can't give a verdict, no fallback for a configured hub |
| 3 | 422 rules miss inputs the checker rejects | OPEN | Unicode whitespace still gets through "trimmed" and "not empty" (see below) |
| 4 | A title's final parentheses turning into metadata | OPEN | Settled for titles in a request; not for titles already in the file when an operation removes the last key (see below) |
| 5 | Mixed endings, lone CR, the final newline | OPEN | Per-line terminators and the dominant terminator are settled. Moving the last block of a file with no final newline is not (see below) |
| 6 | Stored hash and indexed rows disagreeing | SETTLED | One transaction for `source_files` and rows; pages read etag and rows in one transaction; sync takes the file lock |
| 7 | Which etag an edit sends after a poll | SETTLED | Captured when the edit starts; polling pauses during edits |
| 8 | pipeline.md format and write rules | SETTLED | Table detection, header order, format errors, partial PATCH, Add cases, dates and the board layout are all defined |
| 9 | Demo hub paths | OPEN | Relative paths and temp copies are defined, but per-test temp copies can't be "the demo hub" as the spec defines it (see below) |
| 10 | Tick, cancel, move and undo when a key already exists | SETTLED | "Set" and "Remove" are defined; tick and cancel clear each other's keys; undo adds missing required keys or returns 422 |
| 11 | "At most one" proof line | SETTLED | Several allowed, the last counts, as in the checker; Edit keeps order |
| 12 | Cancel adds `from`, Undo removes `cancelled` | SETTLED | Recorded as [D26] |
| 13 | Padded IDs | SETTLED | Lookup and uniqueness by number |
| 14 | Status codes for disallowed cases | SETTLED | State checks 422, missing file 409, task not in the file read 404 |
| 15 | Edit semantics and line layout | SETTLED | Partial PATCH per field; changed task lines rendered in a fixed form |
| 16 | Timezone for "today" | SETTLED | `MARKBOARD_TIMEZONE`, default UTC |
| 17 | Lock, temp file, symlinks | SETTLED | TTL, wait, 503 with `Retry-After`, temp-file name and cleanup, real-path resolution. New problems with the lock numbers are N1 |
| 18 | Conflict lifecycle | SETTLED | Each listed point now has a rule. A gap in Apply's refusal is N3 |
| 19 | `file_versions` retention and `--fresh` | SETTLED | `last_seen_at`; history tables keyed by path and kept by `--fresh` |
| 20 | Search over notes; MySQL vs SQLite | SETTLED | `notes_text` in the index; query tokenising defined. A flaw in that tokenising is N5 |
| 21 | Scheduled sync | SETTLED | Every minute, `schedule:work` in Sail, first to cut ([D28]) |
| 22 | mtime and size missing a change | SETTLED | The 2-second rule |
| 23 | Invalid values in typed columns | SETTLED for dates and repeats. String lengths are N6 |
| 24 | Parsing the registry | SETTLED | Header detection, malformed rows, duplicates |
| 25 | Page details | SETTLED | Task route, controls and component names |
| 26 | Where pipeline.md lives | SETTLED | [D25]; CONTEXT.md matches |
| 27 | Fixtures vs demo hub | SETTLED | [D27]; the demo's contents are listed |
| 28 | Brief rendering safety and routing | SETTLED | `html_input` escape, unsafe links off, `{date}` constraint, missing-brief behaviour |
| 29 | What is mounted into Sail | SETTLED | `MARKBOARD_PROJECTS_ROOT`; folders outside it show "folder not found". The unset case is N8 |
| 30 | Proof lines T2 to T9 | OPEN | T4, T6, T7, T8 and T9 are now checkable. T2, T3 and T5 are not (see below) |
| 31 | Python vs PHP line splitting | SETTLED | The line model copies the checker's boundaries; a fixture is named |

---

## MAJOR

### 4. Gap (still open): titles already in the file become metadata when an operation removes the last key

Quote (Rewriting a task line): "the title as parsed". (Metadata): "Removing the last key removes the parentheses." (Validation): "refused if it ends in a parenthesised group whose comma-separated parts all start with a known key ... so a title can never turn into metadata."

Problem: The title rule checks only a title sent in a request. A title already in the file is rendered "as parsed" and never checked. This line is valid, and its title is "Chase (waiting Bob)":

`- [x] T5 Chase (waiting Bob) (done 2026-10-01, from Up next)`

Undo removes `done` and `from`, which are its only keys. The parentheses go, and the result is `- [ ] T5 Chase (waiting Bob)` in Up next. The checker now reads a real `waiting` key and a title of "Chase", reports nothing, and the write succeeds. The same happens with Edit `due: null` when `due` is the task's only key. If the group would be invalid as metadata, as in "Release (done)", the write fails with a 500 that the spec calls a Markboard bug. Either way, the title rule's promise doesn't hold for operations that remove keys.

Decision needed: What happens when an operation would leave no metadata on a task whose parsed title ends in a group of known keys: refuse it (which status?), or some other rule. Name a fixture for it.

### 5. Gap (still open): moving the block that holds the file's missing final newline

Quote (Writing): "when a block is inserted after a last line that has no terminator, that line gets the dominant terminator and the inserted block's last line gets none; when the last block is removed, the line that becomes last loses its terminator if the removed block's last line had none."

Problem: The rule covers inserting at the end and removing from the end. It doesn't cover the case that combines them: a block whose last line has no terminator is moved somewhere other than the end. Undo or Reorder of the last task in Done does this, in a file with no final newline. If the moved block keeps its bytes, its last line has no terminator and runs into the next line. After an Undo, for example, you get:

`- [ ] T7 Foo- [ ] T3 Bar (due 2026-10-20)`

The checker reads this as a single task T7, titled "Foo- [ ] T3 Bar", with T3's metadata. T3 is gone. Any T3 proof or note lines now attach to T7, and several proofs are allowed. The checker reports no error, and the PHP parse is clean, so the file is written. The spec also never says whether a rewritten task line keeps its original terminator. The named fixture ("an empty `## Done` as the last line") doesn't exercise this case.

Decision needed: The terminator rule for a block (or a single line) that loses its "last line" position, presumably that it gains the dominant terminator. State whether a replaced or rewritten line keeps its own terminator. Add fixtures for Undo and Reorder of the last block in Done in a file with no final newline.

### 9. Contradiction (still open): per-test temp copies of `demo/` aren't "the demo hub"

Quote (projects.md): "`path` is neither absolute nor (demo only) relative". (The write path): "The bundled copy at `resources/checker/check-tasks.py` is used only for the demo hub. A configured hub without a checker gets `503` on every TASKS.md write." (Demo hub): "With `MARKBOARD_HUB_PATH` unset, the app copies `demo/` to `storage/app/demo-hub/`". (Testing): "Feature (Pest), against a temp copy of `demo/` per test". T8: "the temp hub's TASKS.md".

Problem: "The demo hub" is defined only as the case where `MARKBOARD_HUB_PATH` is unset, and that case always uses the single shared `storage/app/demo-hub/`. A test can point the app at its own temp copy in one of two ways, and both break:
- It sets `MARKBOARD_HUB_PATH`. The copy is then a configured hub. Every relative path is malformed, so every project is skipped. `demo/` has no `scripts/check-tasks.py`, so every TASKS.md write returns 503.
- It leaves the variable unset. Then all tests share one copy, which is not "per test".

The quickest fix a builder would reach for is to fall back to the bundled checker for any hub, or to accept relative paths everywhere. Round 1 (issue 2) ruled out the first of these.

Decision needed: How a test or browser run points at its own copy and still counts as the demo, for example a separate setting for the demo copy's location, or a demo flag. Tie "relative paths allowed" and "use the bundled checker" to that, not to `MARKBOARD_HUB_PATH` being unset.

---

## MINOR

### 3. Gap (still open): Unicode whitespace passes validation and fails the checker

Quote (Validation): "**Every text value** (title, proof, notes, `waiting`, `evidence`): trimmed; ..." and "**Proof and each note**: not empty." and "Anything that passes validation must also pass the checker".

Problem: The checker's whitespace is Python's, which is Unicode-aware:
- An indented line is valid only if the character after `proof: ` or `note: ` is not whitespace in Python's sense.
- Metadata pairs and values are stripped with Python's `str.strip()`.

PHP's `trim()` removes only space, tab, `\n`, `\r`, `\0` and `\x0B`. So a proof or note that starts with U+00A0 (a no-break space, common in pasted text), U+2000 to U+200A, U+202F, U+205F or U+3000 passes "trimmed, not empty". The checker then rejects the indented line, and the write ends in 500. A `waiting` or `evidence` value made only of such characters does the same: the checker sees an empty value.

Decision needed: Which whitespace set "trimmed" and "not empty" use (Python's, so validation matches the checker), and a validation test with a leading no-break space.

### 30. Untestable proof lines (still open): T2, T3, T5

- **T2**: "every validation rule has a test". The spec puts validation in Form Requests ("Requests are validated by Form Requests"), which T6 builds, while T2 is the plain-PHP parser. The Testing section also lists these tests under "Unit (Pest, no app boot)", which can't run Form Requests. And "input that would otherwise produce a file the checker rejects" can't hold for every rule: a 301-character title, or a reorder position out of range, produces nothing the checker rejects.
- **T3**: "round trip gives identical bytes on every pipeline fixture". Testing names fixtures only for TASKS.md, so "every pipeline fixture" passes with a trivial set. That is the flaw issue 30 raised for T2 in round 1. The pipeline cases the spec depends on aren't named: a header in a different column order, free text before and after the table, CRLF, no final newline after the last row, and an empty table.
- **T5**: "the ui-foundations page check (check_page.py) passes on each page at 375 and 1440 wide, in light and dark". The script lives outside the repo. It takes a local HTML file path, not a URL (it turns its argument into a `file://` URI). An Inertia page is rendered in the browser, so no static file holds the page. Its widths come from `--widths`, with defaults 320, 375, 768 and 1440. It checks contrast in light and dark only at 1440, and sideways scroll only in light. So "375 ... in dark" is never checked.

Decision needed: Where the validation rules live and which task tests them (T2 or T6), with wording that fits rules that don't map to a checker error. A list of pipeline fixtures for T3. For T5, how a rendered page reaches `check_page.py` (a saved, fully rendered HTML snapshot, or a change to the script to take a URL) and which width and scheme combinations it must pass.

### N1. Gap: the write lock is not re-entrant, and its TTL equals the checker timeout

Quote (The write path, step 1): "a Laravel cache lock named after the real path, held for at most 10 seconds". Step 5: "runs with a 10-second timeout". Step 9: "Re-sync the file (still holding the lock)". Step 2: "stop, re-sync the file, record a conflict". (Sync, step 2): "take the file's lock (the same lock as writes; if it's busy, skip the file this time, since the holder will sync it)".

Problem:
- Laravel cache locks are owner-based and not re-entrant. If steps 2 and 9 call the sync routine as written, it finds the lock busy (the write itself holds it) and skips the file. The re-sync then does nothing, and the 412 body or the next page shows the old rows. Sync's mtime-and-size shortcut (step 1) can skip the file too.
- A checker run close to its 10-second timeout outlives the 10-second lock. A second write to the same file can then take the lock and run alongside the first. Step 7 catches most of these cases, but not all, and "a lock stops two app requests writing the same file at once" [D3] no longer holds.

Decision needed: That the write path's re-syncs skip lock acquisition and the mtime/size shortcut, and a lock TTL longer than the checker timeout plus the rest of the write (or a shorter checker timeout).

### N2. Contradiction: conflict ids in the API vs "database ids never appear"

Quote (Data model): "Database ids never appear in URLs or the API [D13]." With (Conflicts): "The `412` body holds the conflict (id, and whether it can be applied)" and (API): "`POST /api/v1/conflicts/{conflict}/apply`".

Problem: Conflicts have no natural key: their history is keyed by path and many can exist per file. The spec exposes an id while forbidding database ids.

Decision needed: Whether conflicts are an exception to [D13] (narrow the statement to index rows), or what public key they get instead (a UUID column, say).

### N3. Gap: Apply on a conflict that can't be applied

Quote (Conflicts): "**Can be applied** when the base version is still stored and the operation's lines are unchanged between base and current" and "**Apply** needs `If-Match` with the current `etag` from the conflict panel ... It runs the same operation through the write path." (API, 409): "the conflict is already resolved".

Problem: The spec doesn't say:
- what Apply returns for a conflict that can't be applied (the base version was pruned, or the lines changed), or for a superseded one
- whether the server re-checks "can be applied" against the version named in `If-Match`. Conflicts also appear in page props, and pages poll, so the panel can carry a newer etag than the conflict's `disk_hash`. If Apply only checks `If-Match`, an Edit can be applied over a change to that task made after the conflict was recorded, which the user's diff never showed. That is the case [D2] rules out.

Decision needed: Whether Apply re-checks applicability between `base_hash` and the `If-Match` version, the status code for "not applicable" and "superseded", and which version's diff the panel shows.

### N4. Contradiction with [D6]: what "not migrated" means

Quote (Sync, step 4): "A registered project with no TASKS.md is shown as "not migrated"". (Projects/Index): "or "not migrated" / "folder not found" / the error count". With [D6]: files with format errors are marked "not migrated" with the error count.

Problem: [D6] uses the label for files with format errors. The spec uses it only for a missing TASKS.md and shows a file with errors as a bare error count. A builder can't satisfy both.

Decision needed: Which states carry the "not migrated" label. Update [D6] or the spec to match.

### N5. Gap: the search tokenising breaks on hyphens, and stopwords break the "same on both" claim

Quote (Data model, Search): "characters other than letters, digits and `-` are dropped; on MySQL each remaining word of 3 or more characters becomes `+word*` in boolean mode" and "Feature tests search for whole words of 4 or more letters, which behave the same on both."

Problem:
- MySQL's FULLTEXT tokenizer splits indexed text at `-`, and boolean mode reads a `-` in the query as the exclude operator. So "self-hosted" becomes `+self-hosted*`, which asks for "self" and excludes "hosted". It misses the very rows that contain the word.
- InnoDB's default stopword list includes 4-letter-plus words such as "with", "from", "that", "this", "about", "what", "when", "where" and "will". The SQLite `LIKE` fallback matches them and MySQL doesn't, so "whole words of 4 or more letters" don't behave the same on both.

Decision needed: Whether `-` is dropped or treated as a word separator (or the word is quoted), and either a stopword setting (`innodb_ft_enable_stopword`, or an empty stopword table) or a rule that tests avoid stopwords. Add a hyphenated-word test that runs in CI on MySQL.

### N6. Gap: column lengths, and what sync does when a file can't be indexed

Quote (Data model): "`waiting`, `evidence`, `from_section` (nullable strings)" and `pipeline_rows`: "`company`, `role`, `stage`, `next_action`" with no type. (Pipeline validation): "`company`, `role`, `next_action` not empty, and ... contain no line boundary, control character or `|`". (Sync): "On every web and API request, through middleware".

Problem: The default `string` column is `VARCHAR(255)`, and `config/database.php` sets MySQL `strict` to true. A next action longer than 255 characters, typed by hand or by an agent, or sent through the API (there is no maximum), makes the insert fail. Sync then fails for that file on every request. Markboard can also write a value through its own API that it then can't index. The spec doesn't say whether sync failing on one file breaks every page, or is recorded as that file's error while the others carry on.

Decision needed: Types or lengths for these columns, plus matching maximums in validation, or `text` columns. Also what sync does when one file can't be indexed.

### N7. Contradiction: the concurrency test's window includes the accepted race

Quote (Testing): "a test changes the file between steps 2 and 8 of the write path and checks that nothing is written and a conflict is recorded." With (The write path): "Steps 7 and 8 leave a window of microseconds in which another process could save the file and lose its change ... this is accepted".

Problem: A change made after step 7's re-hash and before step 8's rename is overwritten by design, so a test that injects there fails. The spec also doesn't say how a test reaches into the middle of the write path. That needs a seam such as an event or an injectable hook.

Decision needed: Narrow the test to "between steps 2 and 7" (and maybe one injection point in each of 2 to 5 and 6 to 7), and name the seam the test uses.

### N8. Gap: Sail with `MARKBOARD_PROJECTS_ROOT` unset, and a configured hub that isn't there

Quote (Configuration): "`MARKBOARD_PROJECTS_ROOT` ... unset: nothing mounted". (Running): "`compose.yaml` mounts `MARKBOARD_PROJECTS_ROOT` read-write at the same absolute path in the app container". T7: "on a fresh clone, the README's commands bring up the app on localhost with the demo data".

Problem: Compose has no conditional volumes. A mount of `${MARKBOARD_PROJECTS_ROOT}:${MARKBOARD_PROJECTS_ROOT}` with the variable unset becomes `:` and `sail up` fails, which breaks T7 on a fresh clone. The spec also doesn't say what the app shows when `MARKBOARD_HUB_PATH` is set but the folder or its `projects.md` isn't there. That happens, for example, when the hub isn't under the mounted root.

Decision needed: How the volume behaves when the variable is unset (a default path, or a compose override file the README adds), and the app's response to a missing hub or a missing `projects.md` (a page-level error, or an empty Projects page with a message).

---

## Checked, no issue

- Checker claims in the spec, against the source:
  - It exits 0 unless it crashes and prints one line per error.
  - It opens the file as UTF-8 with universal newlines and splits it with `splitlines()`.
  - Several proof lines are allowed; the last one counts.
  - Duplicate IDs are found by number.
  - `from` is limited to the three open sections.
  - Done needs `done`, or `cancelled` without `done`.
  - Final parentheses are metadata only when every key is known.
- The rules for Tick, Cancel and Undo all produce checker-valid lines, and they match [D26]. Undo filling in a missing `started`, `waiting` or `since` goes beyond [D26]. It is needed for the checker to pass, and a builder won't trip on it, so it isn't counted.
- Not counted, because it is a change on the hub side: Cancel now writes `from`. By its stated rules, the hub's planned undo tool would then accept a cancelled task and leave `cancelled` on it. The checker allows that, and Markboard's Tick later removes it. Worth a look when that tool is built.
- `storage/app/demo-hub/` and `storage/app/check/` are ignored by the existing `storage/app/.gitignore`.

---

## Round 3

Narrow review of the edits made for round 2's 13 open issues: the spec, `TASKS.md` (T2, T3, T5 proofs) and [D29]. Checked against the checker's source, the page-check script T5 names, and the repo (`compose.yaml`, `storage/app/.gitignore`, `phpunit.xml`, CI, the design system's dark-mode CSS). The rest of the spec was not re-reviewed.

The first line now counts 2 round 2 issues still open (9 and 30, both narrowed to MINOR) and 3 new ones (R1 to R3).

### The 13 issues from round 2

| # | State | Why |
|---|---|---|
| 3 | SETTLED | Text values are now "trimmed of every character Python's `str.isspace()` counts as whitespace". The checker's `\S` after `proof: `/`note: ` and its `strip()` of metadata both use that same Unicode set, so a value that isn't empty after this trim isn't empty to the checker. A leading no-break-space test isn't named, but "every validation rule has a test" requires one. |
| 4 | SETTLED | "Every rendered line must read back as written ... the operation is refused with `422` and a message to change the title first." This covers Undo and Edit `due: null` on "Chase (waiting Bob)", and the "Release (done)" case, which now gets a 422 instead of a 500. A fixture is named ("a title that would turn into metadata if its last key were removed"). |
| 5 | SETTLED | "If the file's last line has no terminator, an operation first gives it the dominant terminator (in memory), works on the lines, and then removes the terminator from whatever line ends up last." Moving the last block in Done can no longer join two lines. Still unstated: whether a rewritten task line keeps its own terminator or takes the dominant one. Either choice gives a valid file and passes the named tests, so it isn't counted. |
| 9 | OPEN (MINOR) | Settled by [D29] and "Demo-kind hubs", but write path step 5 kept the old wording. See below. |
| 30 | OPEN (MINOR) | T3 and T5 are settled. T2 still has a requirement that can't be met, and a new ordering problem. See below. |
| N1 | SETTLED | The lock TTL is 30 s against a 10 s checker timeout. Step 9 "calls sync's per-file step directly, so it doesn't try to take the lock again", and steps 2 and 7 do the same. The mtime/size shortcut can't hide a fresh write: the rename gives the file a new mtime, and the 2-second rule covers a write in the same second. |
| N2 | SETTLED | "Index ids never appear in URLs or the API [D13] ... Conflicts are the exception." Not counted: [D13] still says "never database ids", and unlike the round 2 changes ([D25] to [D29]), this exception isn't recorded in decisions.md. The spec states it plainly, so a builder won't trip on it. |
| N3 | SETTLED | Apply refuses resolved or superseded conflicts with 409, returns 412 when the disk isn't the `If-Match` version, then re-checks "can be applied" between base and disk, with 409 if that fails. That re-check stops an edit landing on task lines changed since the base, whichever etag the panel carried. Which diff the panel shows after a poll is still unstated, but the re-check makes that safe. The conflict that Apply's 412 creates has a new gap: R1. |
| N4 | SETTLED | Sync step 4 now gives "not migrated" [D6] to both a missing TASKS.md and a file with errors, with "no TASKS.md" or the count. Projects/Index matches. |
| N5 | SETTLED | The query is split at every character that isn't a letter or digit, so `-` and every boolean operator are gone. "self-hosted" becomes `+self* +hosted*`, which matches how MySQL tokenises the indexed text. Feature tests use a word of 6 or more letters that isn't a stopword. |
| N6 | SETTLED | File text goes in `TEXT` columns, with the exceptions listed. Validation has maximums for tasks and pipeline rows. A failed sync transaction rolls back and is recorded in `sync_error` while other files carry on. The new path width causes R2. |
| N7 | SETTLED | "through a test hook that runs between steps 4 and 7"; the 7-to-8 window is explicitly not tested. |
| N8 | SETTLED | The compose mount defaults to `./storage/app/no-projects` at `/mnt/no-projects`, and "Hub missing" covers a hub folder or `projects.md` that isn't there. The placeholder folder causes R3. |

### MAJOR

#### R1. Gap (new): the base of the conflict that Apply's 412 creates

Quote (Conflicts): "Every `412` stores a conflict: ... the hash the user had (`base_hash`) and the hash on disk (`disk_hash`)." and "**Apply** ... if the disk hash isn't the `If-Match` hash, `412` (this conflict is superseded and a new one created). Otherwise it re-checks "can be applied" between the base version and the version on disk now".

Problem: The spec doesn't say what `base_hash` the new conflict gets. By the general rule, "the hash the user had" in an Apply request is the `If-Match` hash. But the operation was written against the original conflict's base, and pipeline rows are addressed by position. If the new conflict's base is the `If-Match` version, the history between the original base and that version is never checked again. A pipeline example:
- The user edits row 3 at version A. An agent inserts a row above it, giving version B. Row 3 is now another company, so conflict C1 (A to B) can't be applied.
- Another change, C, touches other rows. Apply on C1 is called with `If-Match` B (the API allows calling Apply on a conflict that can't be applied).
- The disk is at C, so the call returns 412 and creates C2 with base B.
- C2 can be applied, because row 3 is unchanged between B and C. Applying it writes the user's edit, meant for A's row 3, onto a different company's row.

The same path lets a task Edit land on task lines that changed between A and B. This is the overwrite [D2] rules out. Through the UI the window is narrow (the panel hides Apply on a conflict that can't be applied), but the API doesn't block it.

Decision needed: Whether a conflict created by Apply's 412 keeps the original conflict's `base_hash` (and operation), or Apply refuses a conflict that can't be applied before it compares hashes, or both.

### MINOR

#### 9. Contradiction (still open, narrowed): step 5 still restricts the bundled checker to "the demo hub" and refuses any configured hub

Quote (The write path, step 5): "The bundled copy at `resources/checker/check-tasks.py` is used only for the demo hub. A configured hub without a checker gets `503` on every TASKS.md write." With (Demo-kind hubs): "when it has no `scripts/check-tasks.py`, the bundled checker is used" and (Testing): "a temp copy of `demo/` per test (demo-kind, with `MARKBOARD_HUB_PATH` pointed at the copy)".

Problem: A test's temp copy is a configured hub with no checker. Read literally, step 5 makes every TASKS.md write in the feature tests and the T8 browser test return 503. "The demo hub" is also what the Demo hub section calls the single copy made when `MARKBOARD_HUB_PATH` is unset. [D29] makes the intent clear, and the tests would fail loudly, so this is now MINOR.

Decision needed: Confirm that step 5 follows [D29] (the bundled checker for any demo-kind hub without its own; 503 for a real hub without one), and word step 5 in terms of demo-kind and real hubs.

#### 30. Untestable proof (still open, narrowed): T2's validation tests, and T2's dependence on `demo/`

T3 is settled: its proof points at the pipeline fixtures that Testing now names. T5 is settled. Its proof now lists what check_page.py actually checks: contrast in light and dark, sideways scroll at its four default widths, and text spacing. `npm run snapshot` gives the script a file to load. The design system's dark theme follows `prefers-color-scheme`, which the script emulates.

T2, quote (Testing): "Every validation rule has a test with input that would otherwise produce a file the checker rejects." The length rules (a title over 300 characters, a proof over 500) and the State rules (moving a task to its own section, a reorder position out of range) guard against nothing the checker rejects, so this can't be met as worded. T2's proof drops the clause ("every validation rule has a test"), so the two disagree on what counts as a pass. The State rules also sit under Validation, whose rules "live in a plain PHP class, `app/Markdown/InputRules` ... unit-tested in T2". But they need the parsed file and run at write path step 3, and the spec doesn't say whether InputRules owns them.

New from the T2 edit, quote (T2 proof): "the Unit and Parity tests listed under Testing in docs/spec.md pass". (Testing, Parity): "on every fixture and every demo file". `demo/` doesn't exist in the repo yet, and it is built in T7, so T2 can't be proved in task order.

Decision needed: Wording for the validation-test requirement that fits rules with no matching checker error. Whether the State checks belong to InputRules (tested in T2) or to the actions (tested in T6). Whether `demo/` is built before T2, or T2's Parity proof covers the fixtures only.

#### R2. Conflict with MySQL (new): a `VARCHAR(768)` path can't be part of a composite unique index

Quote (Data model, lengths): "`path` columns, `VARCHAR(768)` so they can be indexed under utf8mb4". (`file_versions`): "unique on (`path`, `hash`)".

Problem: 768 characters × 4 bytes is 3072 bytes, which is InnoDB's entire key limit. Adding `hash` (64 hex characters, 256 bytes in utf8mb4, or 64 in ascii) pushes the key over the limit. MySQL refuses the migration with "Specified key was too long; max key length is 3072 bytes". Local tests run on SQLite (`phpunit.xml`) and pass. CI runs on MySQL and fails at the first migration. `source_files.path`, unique on its own, fits exactly.

Decision needed: How `file_versions` gets uniqueness on path and hash under the key limit, for example a narrower path limit, a hash of the path as the indexed column, or a prefix index. If the path limit changes, the matching "A longer path makes the row malformed" rule changes with it.

#### R3. Conflict with the repo (new): the placeholder folder's `.gitkeep` is git-ignored

Quote (Running): "`compose.yaml` mounts `${MARKBOARD_PROJECTS_ROOT:-./storage/app/no-projects}` ... unset, an empty placeholder folder (tracked with a `.gitkeep`) is mounted".

Problem: The existing `storage/app/.gitignore` ignores everything except `private/`, `public/` and itself. A plain `git add` refuses `storage/app/no-projects/.gitkeep`, so the folder isn't on a fresh clone, which is the case T7's proof tests. Docker usually creates a missing bind-mount source on its own, as root on Linux hosts, so `sail up` may still work. But the spec's "tracked" doesn't hold, and T7 would pass or fail on behaviour the spec didn't choose.

Decision needed: Whether to add an exception for `no-projects/` to `storage/app/.gitignore`, move the placeholder outside `storage/app/`, or rely on Docker creating the folder and drop "tracked with a `.gitkeep`".


## Answers (2026-10-06, after round 3)

- **#9**: write path step 5 now says a demo-kind hub without a checker uses the bundled copy, and only a real hub without one gets 503.
- **#30**: Testing now splits format-guarding value rules (tested against the checker), length rules (boundary tests) and state checks (action classes, T6). Parity runs on demo files once `demo/` exists; that check moved into T7's proof.
- **R1**: a conflict created by Apply's 412 keeps the original `base_hash`; only `disk_hash` is new.
- **R2**: paths are `TEXT`, and path-keyed tables index `path_hash` (SHA-256 of the path), so unique keys stay under InnoDB's limit.
- **R3**: the placeholder mount is `docker/no-projects/` with a tracked `.gitkeep`, outside `storage/`.
- **Leftovers**: a rewritten line keeps its own terminator (#5); conflict ids as an exception to D13 is recorded as decisions.md 30 (N2); the N3 diff question is left to the builder, since Apply's re-check makes either choice safe.
