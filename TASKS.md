# Markboard tasks

Build tasks for Markboard. Spec: docs/spec.md. Hub: decisions round 11, task T32.

## Up next
- [ ] T1 docs/spec.md: views, data model, sync, write-back rules, conflicts and the REST API
  proof: spec-reviewer on docs/spec.md returns CLEAN, or each remaining issue is answered in the spec
- [ ] T2 TASKS.md parser and writer in app/Markdown with Pest round-trip tests
  proof: parse then write returns identical bytes for every fixture; each edit's line diff touches only that task; the PHP parser reports the same errors as check-tasks.py on every fixture
- [ ] T3 Pipeline table parser and writer with Pest tests
  proof: round trip gives identical bytes; changing a stage or adding a row touches only that row
- [ ] T4 Sync into MySQL: migrations, models, the sync service and a scheduled markboard:sync command
  proof: feature tests show a changed file re-synced by hash, an unchanged file skipped, and --fresh rebuilding the index from files alone
- [ ] T5 Read views on the design system: Projects, Project, Pipeline and Brief
  proof: Pest feature tests assert each page's Inertia props from the demo hub; at 375 and 1440 wide, dark and light, the pages pass the design system's page check
- [ ] T6 Edits through /api/v1 with ETag and If-Match, and the conflict panel
  proof: API tests show each edit writes only its lines, a stale If-Match gets 412 and no write, and a file with format errors refuses edits; the Project page shows the conflict
- [ ] T7 Demo hub and README: what it is, how to run it with Sail, architecture and design decisions
  proof: on a fresh clone, the README's commands bring up the app on localhost with the demo data
- [ ] T8 End-to-end browser test: tick a task in the UI and see the change in the file
  proof: the Pest browser test passes locally and in CI
- [ ] T9 Private-content check, then make the repo public
  proof: a search of the full git history finds no client names, no real TASKS.md content and no secrets; the GitHub repo is public with CI passing

## In progress

## Waiting on

## Done
