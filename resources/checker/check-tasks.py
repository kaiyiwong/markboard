#!/usr/bin/env python3
"""Check a TASKS.md file against the strict format in docs/spec.md.

Usage: check-tasks.py <path> [--json]

Prints one line per format error ("line N: message"), or the parsed tasks
and errors as JSON with --json. Format errors are output, not a failure:
the script exits 0 unless it crashes.

Other scripts import parse() from here to read tasks on a best-effort basis.
"""

import datetime
import json
import re
import sys

SECTIONS = ["Up next", "In progress", "Waiting on", "Done"]
KNOWN_KEYS = {"due", "started", "waiting", "since", "done", "evidence", "from", "cancelled"}
DATE_KEYS = {"due", "started", "since", "done", "cancelled"}

TASK_RE = re.compile(r"^- \[( |x)\] T(\d+) (.+)$")
INDENTED_RE = re.compile(r"^  (proof|note): \S")
TRAILING_PARENS_RE = re.compile(r"^(.*?)\s*\(([^()]*)\)$")


def split_metadata(rest):
    """Split the text after the task ID into (title, metadata dict or None).

    The final parentheses are metadata only if every comma-separated pair
    starts with a known key. Otherwise they are part of the title.
    """
    m = TRAILING_PARENS_RE.match(rest)
    if not m:
        return rest.strip(), None
    title, inner = m.group(1), m.group(2)
    pairs = [p.strip() for p in inner.split(",")]
    keys = [p.split(" ", 1)[0] for p in pairs]
    if not pairs or not all(k in KNOWN_KEYS for k in keys):
        return rest.strip(), None
    meta = []
    for p in pairs:
        key, _, value = p.partition(" ")
        meta.append((key, value.strip()))
    return title.strip(), meta


def valid_date(value):
    if not re.fullmatch(r"\d{4}-\d{2}-\d{2}", value):
        return False
    try:
        datetime.date.fromisoformat(value)
    except ValueError:
        return False
    return True


def parse(text):
    """Parse TASKS.md text. Returns {"sections": {...}, "errors": [...]}.

    Parsing is best-effort: tasks are collected even when the file has
    format errors, so callers can still use them.
    """
    errors = []
    sections = {name: [] for name in SECTIONS}
    seen_sections = []
    current = None  # section name once the first section heading is seen
    last_task = None
    ids = {}

    def err(n, msg):
        errors.append({"line": n, "message": msg})

    for n, line in enumerate(text.splitlines(), start=1):
        if line.startswith("## "):
            name = line[3:].strip()
            last_task = None
            if name not in SECTIONS:
                if current is None:
                    continue  # free text before the first section
                err(n, f'unknown section "{name}"')
                continue
            if name in seen_sections:
                err(n, f'section "{name}" appears twice')
            seen_sections.append(name)
            current = name
            continue

        if current is None:
            continue  # free text is allowed before the first section

        if line.strip() == "":
            continue

        if line.startswith(" "):
            if not INDENTED_RE.match(line):
                err(n, 'indented line must be "  proof: ..." or "  note: ..."')
            elif last_task is None:
                err(n, "indented line is not under a task")
            else:
                kind, _, value = line.strip().partition(": ")
                if kind == "proof":
                    last_task["proof"] = value
                else:
                    last_task["notes"].append(value)
            continue

        m = TASK_RE.match(line)
        if not m:
            err(n, "text between sections")
            last_task = None
            continue

        box, num, rest = m.group(1), int(m.group(2)), m.group(3)
        tid = f"T{m.group(2)}"  # keep the ID as written, e.g. T08
        title, meta = split_metadata(rest)
        task = {
            "id": tid,
            "num": num,
            "checked": box == "x",
            "title": title,
            "meta": {},
            "section": current,
            "line": n,
            "proof": None,
            "notes": [],
        }
        if num in ids:
            err(n, f"duplicate ID {tid} (first on line {ids[num]})")
        else:
            ids[num] = n

        for key, value in meta or []:
            if key in task["meta"]:
                err(n, f'metadata key "{key}" appears twice')
            if value == "":
                err(n, f'metadata key "{key}" has no value')
            elif key in DATE_KEYS and not valid_date(value):
                err(n, f'bad date for "{key}": {value}')
            elif key == "from" and value not in SECTIONS[:3]:
                err(n, f'"from" must be Up next, In progress or Waiting on, not "{value}"')
            task["meta"][key] = value

        keys = task["meta"].keys()
        if current == "Done":
            if not task["checked"]:
                err(n, "[ ] in Done: every task in Done is [x]")
            if "cancelled" in keys and "done" in keys:
                err(n, "a cancelled task has no done date")
            elif "cancelled" not in keys and "done" not in keys:
                err(n, "Done task needs done (or cancelled)")
        else:
            if task["checked"]:
                err(n, "[x] outside Done")
            if current == "In progress" and "started" not in keys:
                err(n, "In progress task needs started")
            if current == "Waiting on":
                for k in ("waiting", "since"):
                    if k not in keys:
                        err(n, f"Waiting on task needs {k}")

        sections[current].append(task)
        last_task = task

    if seen_sections != SECTIONS:
        missing = [s for s in SECTIONS if s not in seen_sections]
        if missing:
            err(0, "missing section(s): " + ", ".join(missing))
        elif len(seen_sections) == len(SECTIONS):
            err(0, "sections out of order: " + ", ".join(seen_sections))

    errors.sort(key=lambda e: e["line"])
    return {"sections": sections, "errors": errors}


def check_file(path):
    try:
        with open(path, encoding="utf-8") as f:
            text = f.read()
    except FileNotFoundError:
        return {"sections": {name: [] for name in SECTIONS},
                "errors": [{"line": 0, "message": "TASKS.md not found"}], "missing": True}
    result = parse(text)
    result["missing"] = False
    return result


def main(argv):
    args = [a for a in argv[1:] if not a.startswith("--")]
    if len(args) != 1:
        print("usage: check-tasks.py <path> [--json]", file=sys.stderr)
        return 2
    result = check_file(args[0])
    if "--json" in argv:
        print(json.dumps(result, indent=2))
    else:
        for e in result["errors"]:
            print(f'line {e["line"]}: {e["message"]}')
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
