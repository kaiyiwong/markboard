# CONTEXT.md — Markboard

## Identity

**What it is:** A local web dashboard that reads and writes plain-markdown task files and keeps them as the source of truth.
**What it is not:** A project-management service with its own data. No accounts, no hosted database, nothing that lives only in the database.
**Who it's for:** One person working across many projects on their own Mac, and reviewers reading the code as a Laravel + Vue portfolio piece.

---

## Copy rules

- Tone: plain and specific, like a well-kept ledger
- No em dashes — use commas or restructure
- No filler words: "straightforward", "seamless", "powerful", "robust"
- UI labels use the files' own words: Up next, In progress, Waiting on, Done; pipeline stages as written

---

## Key paths

```
markboard/
├── app/Markdown/          — TASKS.md and pipeline parsers and writers (plain PHP)
├── app/Actions/           — one class per write (tick, move, add, ...)
├── app/Http/              — Inertia page controllers, /api/v1 controllers, Form Requests, Resources
├── resources/js/Pages/    — Vue pages (Projects, Project, Pipeline, Brief)
├── design-system/         — Kai's design system v1.2.0 (tokens, base CSS); choices in DESIGN.md
├── demo/                  — a made-up hub (projects.md, TASKS.md files, pipeline, brief) for demos and tests
└── docs/spec.md           — what the app does and the rules it follows
```

---

## Contacts / accounts

- Data source: `MARKBOARD_HUB_PATH` points at a hub folder (projects.md, priorities.md, TODAY.md, briefs/). Each registered project's folder holds its TASKS.md and, for job search, a pipeline.md. Unset, the app works on a copy of `demo/`.
- The app's environment file is on the sandbox credentials list; Claude never opens it.
- Deploy: none. Local only (Sail on localhost).

---

## Current status

Stack set up on 2026-10-06: Laravel 13, Inertia 3, Vue 3 and TypeScript, Pest, Larastan, Pint, Boost, Sail with MySQL and GitHub Actions CI. Spec done (T1). TASKS.md parser, editor and input rules done in app/Markdown (T2), with parity tests against check-tasks.py. Pipeline table parser and writer done (T3). Sync into MySQL done (T4): the index and history tables, sync on every request, `markboard:sync` every minute, and the made-up hub in `demo/` that the feature tests copy. Read views done (T5): Projects, Project, Pipeline and Brief pages on the design system, with feature tests on their props, and `npm run snapshot` plus check_page.py passing on every page. Edits done (T6): eleven /api/v1 endpoints through one write path (`app/Actions/WriteFile.php`: lock, If-Match, editor, checker, temp file, re-hash, atomic rename, re-sync), conflicts stored and replayable with Apply and Discard, and the editing controls and conflict panel on the Project and Pipeline pages. Next: the README and the app's demo copy (T7).
