# DESIGN.md: Markboard

Design system: **v1.3.0**, copied into `design-system/`. Every rule and value lives there, in `design-system/SPEC.md` and the CSS files. This file records only this project's choices and exceptions.

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
| Surface | fills | `<html data-surface="fills">`: a tinted canvas with white surfaces, so each group (a summary, a section, a board) is one surface |
| Shape | soft | `<html data-shape="soft">` |
| Type | loud | `<html data-type="loud">`: the key element at display size |
| Color | quiet | `<html data-color="quiet">`; the accent only marks what you can act on: links, focus, the selected task, the primary button |

Feel: A calm workbench: each page leads with the one number it's about, then the work in clear groups, quiet enough to keep open all day.

Key element per page: Projects, open tasks across active projects; Project, "2 of 6" done with a progress bar; Pipeline, open applications; Brief, the brief's day ("Thursday, Oct 8"), with each section on its own surface and its count.

Signature: the task row, with its ID, title, due chip and plain-text facts, which ticks into Done: it lands at the top of Done from the direction it came, with a brief accent tint (any task that changes section does the same, including one an agent moved).

Built here from the system's tokens, not yet in the system (candidates for it): the progress meter (`resources/js/Components/Progress.vue`, one series in `accent-strong` on a `bg-control` track) and the status dot on the Projects summary. Motion tokens come from the motion-tokens skill (`resources/css/motion/tokens.css`, tokens and levels only).

History: v1.2.0 with surface `lines` and type `quiet` ("a well-ruled ledger") passed every check but read as words and lines, with no key element; T12 (2026-10-08) changed the two dials and the composition.

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

- A dial alone doesn't give a page character. With every dial quiet and no key element, the pages passed compliance and failed the character check (SPEC 14): set a key element per page and name where the signature shows.
- Under fills, a field on the tinted canvas (the Projects filters) has little contrast with its fill; it stays identifiable by its label and fill (SPEC 3.5 rule 6), but fields read best on a white surface.
