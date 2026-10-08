# Design system

v1.3.0. The starting point for every project: tokens, type, spacing, grid, color, controls and composition rules.

## Use it in a project

1. Copy this folder into the project as `design-system/`.
2. Load, in this order:

```html
<link rel="stylesheet" href="design-system/fonts.css">
<link rel="stylesheet" href="design-system/colors.css">
<link rel="stylesheet" href="design-system/tokens.css">
<link rel="stylesheet" href="design-system/palettes.css">
<link rel="stylesheet" href="design-system/character.css">
<link rel="stylesheet" href="design-system/base.css">
```

3. Set the project's choices on `<html>` and record them in the project's DESIGN.md:

```html
<html lang="en" data-accent="blue" data-neutral="slate" data-surface="layered" data-shape="soft" data-type="loud" data-color="quiet">
<!-- add data-voice="serif" for Source Serif 4 headings. The four character dials are in SPEC.md 14 -->
```

For an accent or neutral outside the default set, also load `colors/<name>.css`.

4. Dark mode: add `class="dark"` or `class="light"` to `<html>`, or neither to follow the OS.

5. Set up the lint (below).

## Lint

`checks/lint/` holds a stylelint config that fails on raw values: hex and named colors, scale steps (`--slate-9`) outside token files, and raw spacing, radius, font size, weight and shadow. It also caps each component at 3 font sizes (SPEC 4.5 rule 8). Token files and `design-system/` are skipped.

In the project:

1. `npm install -D stylelint postcss-html postcss-value-parser`
2. Add a script to `package.json`, and run it from `build` (or `check`) so a raw value fails the build:

```json
"lint:css": "stylelint --config design-system/checks/lint/stylelint.config.mjs \"src/**/*.{css,astro,vue,svelte,html}\""
```

3. So Claude sees errors right after editing a stylesheet, add this hook to the project's `.claude/settings.json` (needs `jq`):

```json
"hooks": { "PostToolUse": [ { "matcher": "Edit|Write|MultiEdit", "hooks": [ { "type": "command", "command": "bash \"$CLAUDE_PROJECT_DIR/design-system/checks/lint/lint-css.sh\"" } ] } ] }
```

A special case keeps its value with a reason after `--`. A disable with no reason fails the run:

```css
/* stylelint-disable-next-line ds/font-size-count -- type specimen shows every role */
```

## Files

- `SPEC.md`: every rule and the reason for it. Read this first.
- `specimen/index.html`: renders every token. Open it after any change.
- `checks/run.mjs`: contrast for every accent and neutral in both modes, reflow, longest words, grid alignment and text spacing. Run `npm install` then `npm run check`.
- `checks/lint/`: the lint (above), its fixtures, and `test.mjs`, which proves every rule fires. Run `npm run check:lint` after changing a rule.

## Changing the system

Change the CSS, update SPEC.md, bump the version in `package.json` and `CHANGELOG.md`, and run the checks. Projects keep the version they copied until they choose to upgrade.
