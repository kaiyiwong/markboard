# Design system

v1.2.0. The starting point for every project: tokens, type, spacing, grid, color, controls and composition rules.

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

## Files

- `SPEC.md`: every rule and the reason for it. Read this first.
- `specimen/index.html`: renders every token. Open it after any change.
- `checks/run.mjs`: contrast for every accent and neutral in both modes, reflow, longest words, grid alignment and text spacing. Run `npm install` then `npm run check`.

## Changing the system

Change the CSS, update SPEC.md, bump the version in `package.json` and `CHANGELOG.md`, and run the checks. Projects keep the version they copied until they choose to upgrade.
