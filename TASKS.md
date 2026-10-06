# Markboard tasks

Build tasks for Markboard. Spec: docs/spec.md. Decisions: docs/decisions.md.

## Up next
- [ ] T3 Pipeline table parser and writer with Pest tests
  proof: the pipeline fixtures named under Testing in docs/spec.md round-trip to identical bytes; editing a row touches only that row, in the header's column order; adding a row works on the empty-table fixture; each format-error fixture is read-only
- [ ] T4 Sync into MySQL: migrations, models, the sync middleware and service, markboard:sync and its schedule
  proof: feature tests show a changed file re-synced by hash, an unchanged one skipped, the 2-second rule, registry and priorities parsed with malformed rows listed, and --fresh rebuilding the index while keeping file_versions and conflicts; schedule:list shows markboard:sync every minute
- [ ] T5 Read views on the design system: Projects, Project, Pipeline and Brief
  proof: Pest feature tests assert each page's Inertia props from the demo hub; npm run snapshot saves each page's rendered HTML from the running app with its CSS inlined, and the ui-foundations check_page.py passes on every snapshot (contrast in light and dark, no sideways scroll at 320, 375, 768 and 1440, text spacing)
- [ ] T6 Edits through /api/v1 with ETag and If-Match, and the conflict panel
  proof: API tests cover every endpoint and every status code in the API section of docs/spec.md, apply and discard, and the concurrency test from Testing; the Project and Pipeline pages show an open conflict with its diff
- [ ] T7 Demo hub and README: what it is, how to run it with Sail, architecture and design decisions
  proof: on a fresh clone, the README's commands bring up the app on localhost with the demo data, and the README states the AI assistance as decisions.md D24 describes; the parity test also runs on every demo file
- [ ] T8 End-to-end browser test: tick a task in the UI and see the change in the file
  proof: the Pest browser test ticks a task in the UI and finds the change in the temp hub's TASKS.md, locally and in CI (Chromium installed in the workflow)
- [ ] T9 Private-content check, then make the repo public
  proof: git log -p --all searched with the private word list (kept outside this repo) finds nothing, gitleaks finds no secrets in the history, and the GitHub repo is public with CI passing

## In progress
- [ ] T2 TASKS.md parser and writer in app/Markdown with Pest round-trip tests (started 2026-10-06)
  proof: the Unit and Parity tests listed under Testing in docs/spec.md pass, covering every fixture named there; every operation's result passes check-tasks.py; every validation rule has a test

## Waiting on

## Done
- [x] T10 CI green: Inertia page path pinned to resources/js/Pages (done 2026-10-06, from Up next)
  proof: the CI run on the T1 pull request passes
  note: tests passed on the Mac but failed on Linux, because Inertia 3 looks in js/pages and the folder is js/Pages
- [x] T1 docs/spec.md: views, data model, sync, write-back rules, conflicts and the REST API (started 2026-10-06, done 2026-10-06, evidence docs/spec-review.md, from In progress)
  proof: spec-reviewer on docs/spec.md returns CLEAN, or each remaining issue is answered in the spec
