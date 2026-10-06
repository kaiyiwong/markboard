# DESIGN.md: Markboard

Design system: **v1.2.0**, copied into `design-system/`. Every rule and value lives there, in `design-system/SPEC.md` and the CSS files. This file records only this project's choices and exceptions.

## Choices

| Setting | Value | How it's applied |
|---|---|---|
| Preset | product | Every screen sits inside `.product` |
| Accent | cyan | `<html data-accent="cyan">` |
| Neutral | slate | `<html data-neutral="slate">` |
| Voice font | off | No `data-voice` |
| Main font | Inter | |
| Languages | en | `lang="en"` on `<html>` |
| Dark mode | follows OS, with toggle | Class on `<html>` |

## Character

How this project looks, beyond following the rules. See `design-system/SPEC.md` 14.

| Dial | Value | How it's applied |
|---|---|---|
| Surface | lines | `<html data-surface="lines">` |
| Shape | soft | `<html data-shape="soft">` |
| Type | quiet | `<html data-type="quiet">` |
| Color | quiet | `<html data-color="quiet">`; the accent only marks what you can act on: links, focus, the selected task, the primary button |

Feel: A well-ruled ledger: dense, calm rows you can scan all day.

Signature: the task row, with its ID, title, metadata chips and proof line, which ticks into Done.

## Overrides

Values or rules this project changes from the system. Each needs a reason. An override that shows up in two projects should become a system change instead.

| Token or rule | Project value | Reason |
|---|---|---|
| (none) | | |

## Font checklist

Only if the main font is not Inter. See SPEC.md 4.6.

| Check | Result |
|---|---|
| Letter-spacing per role (ink density against Inter) | |
| Longest word fits at 360 | |
| Tabular figures | |
| Trim edge against the letters | |
| Weight color at 500 | |
| Body line height | |

## Never

Project-specific only. System-wide rules are in SPEC.md.

- Never hide a file's format errors behind an empty state: show the file read-only with its errors and line numbers.
- Never leave a change on screen that the file didn't take: after a failed or refused write, the page shows what is on disk.

## Gotchas

[Add entries when something actually goes wrong in this project.]
