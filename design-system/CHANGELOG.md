# Changelog

## 1.2.0

- Character: four dials set per project on `<html>` (`data-surface`, `data-shape`, `data-type`, `data-color`), in the new `character.css`, and six composition rules (SPEC 14). With no dial set, a page looks as it did in 1.1.0.
- Fields: text fields read `--field-*` tokens, so the fills surface can draw them filled with no outline. A field is identified by a 3:1 edge, or by a visible fill plus a visible label (SPEC 3.5 rule 6). Checkboxes, radios, switches and segmented controls keep their 3:1 edges.
- Labels: one field label role (`.p-field`, and `.ctl-label` beside a control), 14px, medium, default color. Row labels were regular and muted; stacked labels used the 12px muted label role, which differed from captions only by weight. The gap from label to field is 8px (was 4).
- Radius by role: `--container-radius` and `--media-radius` join `--control-radius`, and the shape dial sets all three. New `--radius-4` (24). Textareas stop at 16 and checkboxes at 4 at every shape.
- Shadows tested: raised (cards), overlay (menus and popovers) and modal (dialogs), each a 1px ring plus soft layers, with dark versions. `.panel` takes `--panel-shadow`, which only the layered surface sets.
- New roles: stage (`--bg-stage`, `--fg-stage`, `--fg-stage-muted`, `.stage`), key element (`.p-key`), and `-fg-strong` for status text on its own tint.
- Fixed: status text on its own tint failed AA in light mode (success 4.21:1, warning and info 4.25:1). Use `--success-fg-strong` and the others (step 12) there.
- `textarea.input` is now part of base.css.

Upgrading from 1.1.0: replace `design-system/` and add `character.css` to the load order after `palettes.css`. Visible changes with no dial set: field and row labels are darker and 14px, the label gap is 8px, cards on a panel pick up nothing new (no shadow unless layered). Replace status text on a status background with the `-fg-strong` role. Then choose the dials and record them in DESIGN.md.

## 1.1.0

- Chinese headings break between phrases: `word-break: keep-all` with `overflow-wrap: anywhere`, and `<wbr>` at each phrase break (SPEC 10.6). Without `<wbr>`, a heading breaks only where it would overflow, as before. The specimen shows an example, and the checks confirm it breaks only at `<wbr>` at 320, 360 and 375px.
- New SPEC sections: 8.9 View states (loading, empty, error, undo, validation, announcements) and 13 Copy.
- Fixed: a select gets the shared control minimum width (`.select select.input`), and the specimen's contrast swatches are hidden from screen readers. Both fixes had reached the ui-foundations copy but not this source.

Upgrading from 1.0.0: replace `design-system/`. Nothing is renamed or removed; Chinese headings only change where `<wbr>` is added.

## 1.0.0

First version.

- Color: Radix scales, slate neutral, blue accent, roles for light and dark, per-accent button fills and strong steps for every Radix hue, five-color chart palette.
- Type: Inter, optional Source Serif 4 voice, three weights, fluid editorial scale with hero steps, fixed product scale, trimmed text.
- Spacing: ten-level ladder, cap-height text gaps for editorial, heading floor.
- Layout: 4, 8 and 12 column grid, reading-page lines, container rule, cap-top alignment.
- Media: 16:9, 4:3, 3:4, 1:1 ratios.
- Controls: shared sizes, radius tiers, states, toggles, rows of controls, tooltips.
- Chinese and Japanese rules, accessibility rules, automated checks.
- Not yet: motion, icon set, components beyond the basics, tested shadows and stacking order.
