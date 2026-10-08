# Markboard tasks

Build tasks for Markboard. Spec: docs/spec.md. Decisions: docs/decisions.md.

## Up next
- [ ] T8 End-to-end browser test: tick a task in the UI and see the change in the file
  proof: the Pest browser test ticks a task in the UI and finds the change in the temp hub's TASKS.md, locally and in CI (Chromium installed in the workflow)

## In progress
- [ ] T9 Private-content check, then make the repo public (started 2026-10-08)
  proof: git log -p --all searched with the private word list (kept outside this repo) finds nothing, gitleaks finds no secrets in the history, and the GitHub repo is public with CI passing
  note: both scans pass and the MIT license is added (docs/verify/T9.md); still to do: merge, make the repo public, CI green on main

## Waiting on

## Done
- [x] T11 CI green on MySQL: three tests that passed on SQLite failed in CI (done 2026-10-08, evidence 964c57c, from Up next)
  proof: the full suite passes on MySQL 8.4 (Sail's testing database) and on SQLite
  note: a lock taken on the frozen clock had expired by the real one; MySQL's JSON type sorts keys; InnoDB indexes FULLTEXT rows only on commit
- [x] T7 Demo hub and README: what it is, how to run it with Sail, architecture and design decisions (started 2026-10-07, done 2026-10-08, evidence docs/verify/T7.md, from Waiting on)
  proof: on a fresh clone, the README's commands bring up the app on localhost with the demo data, and the README states the AI assistance as decisions.md D24 describes; the parity test also runs on every demo file
  note: demo/ itself was built in T4 (decisions D32); T7 adds the app's demo copy, markboard:demo --reset and the README
  note: Kai brought up a fresh clone with Sail on 2026-10-08, after putting Sail's MySQL settings in .env.example
- [x] T6 Edits through /api/v1 with ETag and If-Match, and the conflict panel (started 2026-10-07, done 2026-10-07, evidence docs/verify/T6.md, from In progress)
  proof: API tests cover every endpoint and every status code in the API section of docs/spec.md, apply and discard, and the concurrency test from Testing; the Project and Pipeline pages show an open conflict with its diff
  note: shown by page-prop tests; Kai clicked through the controls and a conflict in a browser on the demo hub on 2026-10-08
- [x] T5 Read views on the design system: Projects, Project, Pipeline and Brief (started 2026-10-07, done 2026-10-07, evidence docs/verify/T5.md, from In progress)
  proof: Pest feature tests assert each page's Inertia props from the demo hub; npm run snapshot saves each page's rendered HTML from the running app with its CSS inlined, and the ui-foundations check_page.py passes on every snapshot (contrast in light and dark, no sideways scroll at 320, 375, 768 and 1440, text spacing)
- [x] T4 Sync into MySQL: migrations, models, the sync middleware and service, markboard:sync and its schedule (started 2026-10-07, done 2026-10-07, evidence docs/verify/T4.md, from In progress)
  proof: feature tests show a changed file re-synced by hash, an unchanged one skipped, the 2-second rule, registry and priorities parsed with malformed rows listed, and --fresh rebuilding the index while keeping file_versions and conflicts; schedule:list shows markboard:sync every minute
- [x] T3 Pipeline table parser and writer with Pest tests (started 2026-10-06, done 2026-10-06, evidence docs/verify/T3.md, from In progress)
  proof: the pipeline fixtures named under Testing in docs/spec.md round-trip to identical bytes; editing a row touches only that row, in the header's column order; adding a row works on the empty-table fixture; each format-error fixture is read-only
- [x] T2 TASKS.md parser and writer in app/Markdown with Pest round-trip tests (started 2026-10-06, done 2026-10-06, evidence docs/verify/T2.md, from In progress)
  proof: the Unit and Parity tests listed under Testing in docs/spec.md pass, covering every fixture named there; every operation's result passes check-tasks.py; every validation rule has a test
- [x] T10 CI green: Inertia page path pinned to resources/js/Pages (done 2026-10-06, from Up next)
  proof: the CI run on the T1 pull request passes
  note: tests passed on the Mac but failed on Linux, because Inertia 3 looks in js/pages and the folder is js/Pages
- [x] T1 docs/spec.md: views, data model, sync, write-back rules, conflicts and the REST API (started 2026-10-06, done 2026-10-06, evidence docs/spec-review.md, from In progress)
  proof: spec-reviewer on docs/spec.md returns CLEAN, or each remaining issue is answered in the spec
