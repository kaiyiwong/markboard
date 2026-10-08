# Design system v1.3.0: spec

The rules every project starts from. Values live in the CSS files. This file says what they mean, where they apply and why. When the CSS and this file disagree, fix one of them; don't work around it.

## 0. Files and setup

| File | What it holds |
|---|---|
| `fonts.css` + `fonts/` | Inter and Source Serif 4, with their licenses (SIL OFL) |
| `colors.css` | The default Radix scales: slate, blue, red, amber, green, cyan, violet, crimson, yellow, mint |
| `colors/<name>.css` | Every other Radix scale, one file each. `colors/all.css` is for testing only |
| `tokens.css` | All three token tiers, light and dark |
| `palettes.css` | Accent and neutral switches (`data-accent`, `data-neutral`) |
| `character.css` | The character dials (`data-surface`, `data-shape`, `data-type`, `data-color`). See 14 |
| `base.css` | Type roles, trimming, stacks, grid, controls, composition, media, charts, Chinese and Japanese, high contrast |
| `specimen/` | The test page. Open it after any change |
| `checks/run.mjs` | Automated checks. `npm run check` |
| `checks/lint/` | Stylelint config for projects: raw values and the font-size cap fail the build. Setup in README.md |

Load order: `fonts.css`, `colors.css` (plus any extra scale files), `tokens.css`, `palettes.css`, `character.css`, `base.css`.

A project records only its choices in its DESIGN.md: system version, preset (editorial, product or both), accent, neutral, voice font on or off, the four character dials, languages, and any overrides with the reason.

## 1. Principles

1. Components use roles, never raw values. No hex codes or pixel values outside `tokens.css`. The lint in `checks/lint/` enforces this; a special case keeps its value only with a written reason.
2. Spacing belongs to the container. Elements don't carry margins; stacks and grids set the gaps.
3. Text is measured from the letters. Text boxes are trimmed to cap height and baseline, so a gap is the visible gap.
4. Color carries meaning. Neutral by default, accent for interaction and selection, status colors for status only.
5. Nothing is drawn unless it does something.
6. WCAG 2.2 AA is the floor, and `checks/` proves it.
7. Every project has a point of view. The rules keep a page from looking wrong; the character dials and composition rules (14) make it look like something. Leaving every dial on its default is allowed, but it has to be a choice.

## 2. Tokens

| Tier | Examples | Who uses it |
|---|---|---|
| Primitive | `--slate-9`, `--blue-11`, raw sizes | Only the semantic tier |
| Semantic (roles) | `--bg-canvas`, `--fg-muted`, `--border-control`, `--accent-strong`, `--space-7`, `--ed-body` | Components and pages |
| Component | `--control-h-md`, `--control-px-icon-md`, `--control-radius` | Controls |

## 3. Color

### 3.1 Scales

Every hue is a 12-step Radix scale. Each step has one job:

| Steps | Job |
|---|---|
| 1 to 2 | Page and subtle backgrounds |
| 3 to 5 | Component backgrounds: rest, hover, pressed |
| 6 to 7 | Decorative borders and dividers |
| 8 | Strong decorative border |
| 9 to 10 | Solid fills |
| 11 to 12 | Text: muted and default |

Exception: borders that identify a control use step 9 (see 3.4). Steps 6 to 8 don't reach 3:1.

### 3.2 Neutral

Slate by default. Switch with `<html data-neutral="gray">` (or mauve, sage, olive, sand) and include `colors/<name>.css`. Avoid sand and olive for canvas-heavy work: they read as the warm off-white the system avoids.

### 3.3 Accent

Blue by default. Switch with `<html data-accent="violet">` and include `colors/violet.css`. Every Radix hue has a ready block in `palettes.css`, computed with these rules:

- Button fill: the first light-scale step where a white label reaches 4.5:1. Bright hues (amber, lime, mint, sky, yellow) keep step 9 with a dark label. The fill is the same in light and dark mode.
- Hover and pressed darken the fill (88% and 80% mixed with black) so contrast only goes up. Dark-label hues use step 10 for hover.
- `accent-strong`: the first step that reaches 3:1 on the canvas. Used for focus rings, checked controls, selected markers and highlighted chart series.

| Accent | Button fill (light step) | Label | Label contrast | Strong step, light | Strong step, dark |
|---|---|---|---|---|---|
| amber | 9 | dark | 10.38:1 | 11 | 8 |
| blue | 11 | white | 4.77:1 | 9 | 8 |
| bronze | 11 | white | 5.83:1 | 9 | 8 |
| brown | 11 | white | 5.8:1 | 9 | 8 |
| crimson | 11 | white | 5.39:1 | 9 | 8 |
| cyan | 11 | white | 4.76:1 | 9 | 8 |
| gold | 11 | white | 5.91:1 | 9 | 8 |
| grass | 11 | white | 5.07:1 | 9 | 8 |
| green | 11 | white | 4.72:1 | 9 | 8 |
| indigo | 9 | white | 5.21:1 | 9 | 8 |
| iris | 9 | white | 5.37:1 | 9 | 8 |
| jade | 11 | white | 4.66:1 | 9 | 8 |
| lime | 9 | dark | 12.15:1 | 11 | 8 |
| mint | 9 | dark | 11.5:1 | 11 | 8 |
| orange | 11 | white | 4.51:1 | 10 | 8 |
| pink | 10 | white | 4.5:1 | 9 | 8 |
| plum | 9 | white | 4.75:1 | 9 | 8 |
| purple | 9 | white | 5.18:1 | 9 | 8 |
| red | 11 | white | 5.21:1 | 9 | 8 |
| ruby | 11 | white | 5.43:1 | 9 | 8 |
| sky | 9 | dark | 11.06:1 | 11 | 7 |
| teal | 11 | white | 4.56:1 | 9 | 8 |
| tomato | 11 | white | 4.98:1 | 9 | 8 |
| violet | 9 | white | 5.39:1 | 9 | 8 |
| yellow | 9 | dark | 12.96:1 | 11 | 8 |

A custom brand color: generate a scale with the Radix custom palette tool, save it as `colors/<name>.css`, and write a `palettes.css` block with the same rules. Run the checks.

### 3.4 Roles

| Role | Light | Dark | Use |
|---|---|---|---|
| `bg-canvas` | white | neutral 1 | Page background |
| `bg-subtle` | neutral 2 | neutral 2 | Quiet areas |
| `bg-surface` | white | neutral 2 | Panels, menus, dialogs |
| `bg-control` / `-hover` / `-active` | neutral 3 / 4 / 5 | same steps | Secondary buttons, segmented options |
| `border-subtle` / `-default` / `-strong` | neutral 6 / 7 / 8 | same | Dividers, hairlines, panel edges |
| `border-control` / `-hover` | neutral 9 / 10 | same | Outlined fields, checkboxes, radios, switch tracks (3:1) |
| `field-bg`, `field-border`, `field-edge` (and `-hover`) | canvas, border-control | same | Text fields. The surface dial switches them from outlined to filled (14.1) |
| `panel-border`, `panel-shadow` | border-subtle, none | same | Cards and panels. The surface dial switches them |
| `fg-default` / `fg-muted` / `fg-disabled` | neutral 12 / 11 / 8 | same | Text |
| `accent-bg` / `-hover` | accent 3 / 4 | same | Selected backgrounds |
| `accent-fg` / `accent-fg-strong` | accent 11 / 12 | same | Accent text, text on accent backgrounds |
| `accent-solid` / `-hover` / `-active`, `fg-on-accent` | see 3.3 | same | Primary buttons |
| `accent-strong`, `focus-ring` | see 3.3 | see 3.3 | Focus, checked, selected markers |
| `danger-*`, `warning-*`, `success-*`, `info-*` | red, amber, green, blue: bg 3, border 9 (11 for warning), fg 11, fg-strong 12 | same | Status only. `-fg` is text on the canvas or a surface; `-fg-strong` is text on the status's own `-bg` (step 11 on step 3 is under 4.5:1 for green, amber and blue) |
| `danger-solid` | red 11, white label | same | Destructive buttons |
| `scrim` | black 40% | black 60% | Behind dialogs |
| `bg-stage`, `fg-stage`, `fg-stage-muted` | neutral 12, neutral 1, neutral 8 | near black, neutral 12, neutral 11 | The stage: an area that stays dark in both themes, for media (14.2) |
| `shadow-raised` / `-overlay` / `-modal` | a 1px ring plus soft layers | a light ring plus darker layers | Cards on the layered surface, menus and popovers, dialogs. Controls stay flat |

### 3.5 Rules

1. Canvas is pure white in light mode by default. No off-white, no warm tint. The fills and layered surfaces (14.1) tint it to neutral 2. On that canvas, step-11 status text falls under 4.5:1 for green and amber, so status text sits on a surface.
2. Anything that shows a state (selected, checked, invalid) needs a marker that reaches 3:1, not just a tint. Tabs get a 2px `accent-strong` bar, options get a check icon, invalid fields get a red border plus an icon and message.
3. Status colors are for status. Never decorate with them.
4. Dark mode: `<html class="dark">`, `<html class="light">`, or neither to follow the OS. A small script sets the class from the OS setting and a manual toggle. Component code never changes between modes.
5. Contrast gate: WCAG 2.2 AA. Text 4.5:1, controls, markers and chart shapes 3:1. APCA isn't part of WCAG and isn't used as a gate.
6. A control's edge reaches 3:1 when the edge is the only thing that identifies it (WCAG 1.4.11). A text field may instead show a visible fill and a visible label, as the fills surface does. Checkboxes, radios, switches and segmented controls always keep a 3:1 edge. The focus ring always reaches 3:1.

## 4. Typography

### 4.1 Fonts

| Slot | Font | Status |
|---|---|---|
| Main | Inter (weight and optical-size axes) | Default for editorial and product |
| Voice | Source Serif 4 | Optional: `<html data-voice="serif">`. Headline, display, hero and pull quotes only |
| Code and IDs | System monospace | Default |
| System font | system-ui stack | Option for a project that must load nothing extra or feel native to one platform |
| Chinese, Japanese | Noto Sans SC / TC / JP, then system fonts | See section 10 |

Maximum two families on a page, not counting monospace.

### 4.2 Weights

Three weights in the whole system.

| Token | Weight | Use |
|---|---|---|
| `--weight-regular` | 400 | Body, lead, caption |
| `--weight-medium` | 500 | Editorial headings, the label and field label roles, text inside controls, values |
| `--weight-strong` | 600 | Product titles and headings, bold inside text |

No italics anywhere. Emphasis uses 600.

### 4.3 Editorial scale

Fluid from 360 to 1440. Body is step 0; each step multiplies by 1.2 at 360 and 1.25 at 1440. Headline and display skip a step so each level is clearly bigger.

| Role | Step | 360 | 1440 | Line height | Letter-spacing | Use |
|---|---|---|---|---|---|---|
| caption | −1 | 13 | 14 | 1.4 | 0 | Image captions, credits, footnotes. May wrap |
| label | −1 | 13 | 14 | 1.4 | 0 | Small headings, bylines, dates, tags. One line only |
| body | 0 | 16 | 18 | 24 → 28px | 0 | Reading text. Max 65 characters per line |
| lead | +1 | 19 | 23 | 1.4 | −0.005em | Intro paragraph |
| title | +1 | 19 | 23 | 1.3 | −0.005em | Sub-headings, titles in lists of work |
| headline | +3 | 28 | 35 | 1.15 | −0.01em | Section headings, pull quotes |
| display | +5 | 40 | 55 | 1.05 | −0.02em | The page title, one per page |
| hero | +6 | 48 | 69 | 1.05 | −0.025em | Replaces display in a hero |
| hero large | +7 | 57 | 86 | 1.05 | −0.025em | Replaces display in a hero |

Hero rules: one per page, editorial only. If its longest word doesn't fit at 360, drop to display.

### 4.4 Product scale

One fixed set at every width. Line heights sit on the 4px grid.

| Role | Size | Line height | Weight | Letter-spacing | Use |
|---|---|---|---|---|---|
| caption | 12 | 16 | 400 | 0 | Help text, secondary table text. May wrap |
| label | 12 | 16 | 500 | 0 | Column headers, tags, metadata, the label over a stat. Muted. One line only |
| field label | 14 | 20 | 500 | 0 | Names a control, above it or beside it. Default color (`.p-field`, `.ctl-label`) |
| body | 14 | 20 | 400 | 0 | Default UI text, table cells, inputs |
| title | 16 | 24 | 600 | 0 | Card, panel and dialog titles |
| headline | 24 | 32 | 600 | −0.005em | Page titles |
| display | 36 | 44 | 600 | −0.01em | Stat numbers, empty-state titles |
| key | 16 or 36 | 24 or 44 | 600 | 0 or −0.01em | The view's one key number or title (`.p-key`): title size when the type dial is quiet, display size when loud |

### 4.5 Rules

1. Every text element is trimmed: `text-box: trim-both cap alphabetic`. Buttons and labels too.
2. Headings use `text-wrap: balance`; body uses `text-wrap: pretty`.
3. Numbers in tables and stat cards use tabular figures.
4. Inputs are 16px on touch devices (iOS zooms into anything smaller).
5. All-caps labels, if a project uses them, get +0.06em letter-spacing.
6. Letter-spacing is a fixed value per role, never used to squeeze a word into a column.
7. A label and the text that supports it differ in size and color, not weight alone: a field label is 14 in the default color; help text and counters are 12 muted. At 12px, 500 against 400 can't be seen.
8. A component uses at most 3 font sizes. More sizes than that blur the hierarchy, and sizes 1 to 2px apart can't be told apart. A component is one file in single-file formats (Astro, Vue, Svelte, CSS modules), otherwise the first class in a selector. A special case, such as a type specimen, keeps a fourth size with a reason in a `stylelint-disable-next-line ds/font-size-count -- <reason>` comment.

### 4.6 Changing the main font

1. Spacing needs nothing: u reads the new font's cap height.
2. Re-check letter-spacing for headline, display and hero by comparing ink density with Inter at the same words and size.
3. Re-run the longest-word check at 360.
4. Confirm tabular figures exist.
5. Check the trim edge against the letters at 200px.
6. Compare weight color (ink coverage) at 500.
7. Look at body line height; a taller x-height may need more.
8. Add Chinese or Japanese partners if needed.

Serifs don't match a sans's ink density and shouldn't be squeezed to: keep their own spacing (0 to −0.015em at large sizes).

## 5. Spacing

### 5.1 Ladder

| Level | 360 | 1440 | Use |
|---|---|---|---|
| 1 | 4 | 4 | Icon to its label |
| 2 | 8 | 8 | Inside controls, tight clusters, caption below an image |
| 3 | 12 | 12 | Control padding, gap between touch controls |
| 4 | 16 | 16 | Small padding, form rows, hairline to row content |
| 5 | 24 | 24 | Card padding, gaps between cards and control groups |
| 6 | 32 | 32 | Panel padding, between form groups |
| 7 | 40 | 48 | Between blocks inside a section |
| 8 | 48 | 64 | Between sub-sections |
| 9 | 64 | 96 | Between sections |
| 10 | 80 | 128 | Top and bottom of the page, around the hero |

### 5.2 Text gaps (editorial)

Gaps between two pieces of editorial text are measured in u, the body text's cap height (11.6px at 360, 13.1px at 1440 with Inter).

| Gap | Size | Use |
|---|---|---|
| pair | 1u | A label and what it labels |
| para | 2u | Between paragraphs; heading to its body |
| group | 3u | Above a new heading inside a section |
| list | 1u | Items in an editorial list |

- The largest text gap (3u) is always smaller than the smallest layout level (7), so a gap inside a section can't exceed one between sections.
- Floor: the gap below a heading is never smaller than that heading's own line gap (`1lh − 1cap`).
- Implementation: `--u` is a registered length set to `1cap` on the body, so it's computed once and inherited. Use `.stack` with `.stack-pair`, `.stack-para` or `.stack-group`.

### 5.3 Product

Product UI uses the ladder for every gap, including text to text. Text is still trimmed.

## 6. Layout

### 6.1 Grid

| Width | Columns | Gutter | Margin |
|---|---|---|---|
| under 640 | 4 | 16 | 16 |
| 640 to 1023 | 8 | 16 | 32 |
| 1024 and up | 12 | 24 | 48 |

The grid stops growing at 1440; extra width goes to the margins. Everything that has a width (images, columns, components) spans whole columns.

### 6.2 Reading pages

Named lines on `.reading`: `full`, `wide` and `content` (65 characters). Use them for long-form pages instead of the 12 columns.

### 6.3 Containers

The page responds to the window. Components respond to their container: the component root gets `container-type: inline-size` (`.cq`) and switches layout at 480px (compact under, regular from). Only arrangement changes; type roles and control sizes stay the page's.

### 6.4 Alignment and lines

1. Text of different sizes side by side aligns on the cap top.
2. Everything in a row of controls shares one center line.
3. Hairlines separate rows in tables and specimen lists only. Sections and reading text are separated by spacing alone.

## 7. Images and media

| Ratio | Token | Use |
|---|---|---|
| 16:9 | `--ratio-wide` | Default: heroes, video, wide images |
| 4:3 | `--ratio-photo` | Photos |
| 3:4 | `--ratio-portrait` | Portraits |
| 1:1 | `--ratio-square` | Thumbnails, avatars |
| 9:16 | `.ratio-vertical` | Vertical video only |

1. Width follows the grid; height comes from the ratio. Where media the user supplied (uploads, generated results) is the thing being looked at (the stage, a viewer, a detail page), it keeps its own ratio, with width and height attributes reserving the space: cropping or letterboxing it there misrepresents it. Thumbnails in a grid or list crop to one ratio token, aimed at the subject, so rows stay even.
2. Corners follow the shape dial (`--media-radius`): square by default, 8 soft, 16 round. Avatars are round. Inside a rounded container, follow the concentric rule.
3. Reserve space before loading (`aspect-ratio` or width and height). Crop with `object-fit: cover` and aim `object-position` at the subject.
4. Captions: caption role, 8px below, aligned to the image's left edge, max 65 characters.
5. Alt text says what the image shows; decorative images get empty alt text.
6. Video: 16:9, a captions track, no autoplay with sound.

## 8. Controls and components

### 8.1 Control sizes

| | sm | md | lg | xl |
|---|---|---|---|---|
| Height (minimum) | 24 | 32 | 40 | 48 |
| Side padding | 8 | 12 | 16 | 24 |
| Side padding, icon side | 4 | 8 | 12 | 16 |
| Minimum width (2 × height) | 48 | 64 | 80 | 96 |
| Label size | 12 | 14 | 14 (16 on touch) | 16 |
| Icon | 16 | 16 | 20 | 20 |
| Icon to label | 4 | 8 | 8 | 8 |

- Heights are minimums (`min-height`), so text can grow.
- No vertical padding: the trimmed label is centered by the height. Forced wrapping gets a level 2 floor.
- md with a mouse, lg on touch (`.auto` switches). sm is never used on touch.
- Inputs, selects and buttons share heights so they line up.

### 8.2 Radius

| Tier | Token | sharp (default) / soft / round | Used on |
|---|---|---|---|
| Controls | `--control-radius` | 4 / 8 / full | Buttons, inputs, selects, switches, segmented controls, tags |
| Containers | `--container-radius` | 8 / 16 / 24 | Panels, menus, popovers, dialogs, list boxes, the stage |
| Media | `--media-radius` | 0 / 8 / 16 | Images and video |
| Chart bars | `--radius-bar` | 12 | Top corners only, never the baseline |
| Full | `--radius-full` | 9999 | Dots, avatars, and controls under the round shape. No pill buttons otherwise |

The shape dial (14.1) sets the three role tokens. Two caps hold at every shape: a textarea stops at 16 (a pill textarea is never right) and a checkbox at 4 (a round checkbox reads as a radio).

Concentric rule: an element within 8px of its container's edge gets the container's radius minus the gap (never below 0). Radius doesn't grow with control size.

### 8.3 States

| State | Input | Secondary button | Primary button | Ghost |
|---|---|---|---|---|
| Rest | `field-bg`, `field-border` (canvas and `border-control` when outlined; neutral 3 and none when filled) | neutral 3 | `accent-solid` | transparent |
| Hover | `field-bg-hover`, `field-border-hover` (border neutral 10 when outlined; neutral 4 when filled) | neutral 4 | `accent-solid-hover` | neutral A4 |
| Pressed | | neutral 5 | `accent-solid-active` | neutral A5 |
| Disabled | neutral 2, border neutral 6, text neutral 8 | neutral 3, text neutral 8 | same as secondary | text neutral 8 |

1. Focus: `:focus-visible` only; 2px `focus-ring` outline, 2px offset. On colored surfaces, use a color that reaches 3:1 on that surface.
2. Disabled never uses opacity. Prefer keeping submit buttons enabled and explaining errors on submit.
3. Loading: the button keeps its width and label; a spinner replaces the icon; `aria-busy`.
4. All hover styles sit inside `@media (hover: hover)`, including color changes.
5. `.is-hover`, `.is-active`, `.is-focus` and `.is-disabled` exist for documentation pages only.

### 8.4 Touch targets

- Mouse: 24 × 24 minimum (WCAG 2.2 AA). Icon-only buttons are square and never smaller.
- Touch: 44 × 44 hit area (`.hit`), and at least 12px between controls.
- Form choices stack vertically; on touch each row is 44px tall.

### 8.5 Buttons

1. One primary button per view or section.
2. Labels are one line, verb first, sentence case, never truncated, no trailing arrow.
3. Groups: 8px apart (12 on touch), primary last. Below 640, the main actions at the bottom of a form or sheet stack full width with primary on top (`.actions`).
4. Destructive buttons use `danger-solid`.

### 8.6 Toggles

| Component | Use |
|---|---|
| Switch | One setting on or off, taking effect immediately. The label names what's on |
| Segmented control | One choice among 2 to 4 named options, taking effect immediately. Equal widths |
| Toggle buttons | Independent on/off tools that sit together |
| Checkbox, radio | Form choices submitted later |

- Switch: label on the left, switch at the end of the row. 36 × 20 (44 × 24 on touch), control radius, concentric thumb. No "On"/"Off" text and no names on both sides.
- Segmented: selected option inverted (`fg-default` fill, canvas text). More than 4 options becomes a select. Color swatch pickers may show up to 8 chips.

### 8.7 Rows of controls

1. One size per row.
2. Label every group in a row, or none. Prefer controls that show their meaning (icons, color chips); keep words when no icon is clear.
3. Labels sit left of their control and use the field label role: 14, medium, default color, the same as a label above a field. Muted is for text that supports something (help, counters, units). Values inside controls are medium weight. Labels above are for vertical forms.
4. 8px inside a group (12 on touch), 24px between groups.
5. Selects look like inputs, with the system chevron.

### 8.8 Tooltips and icons

- Icon-only controls have an `aria-label` and a tooltip (`data-tip`) shown on hover after 400ms and immediately on keyboard focus.
- Icon size by the text beside it: 16 next to 12 to 14px, 20 next to 16, 24 next to 18 and up.
- 1.5px stroke at every size (`vector-effect: non-scaling-stroke`), drawn on a 24 grid.
- Icons center on the capital letters automatically because text is trimmed.

### 8.9 View states

Anything that loads or holds data (a list, table, chart, search, or a panel that fetches) has
four states: loading, empty, error and full.

1. Loading: the real structure with skeleton bars where text will be, sized so nothing shifts
   when the data arrives. Spinners only inside the button that started an action (8.3.3).
   `aria-busy` on the region while it loads.
2. Empty: says what will appear here and offers one next action. No-results names the search
   and offers to clear it.
3. Error: says what happened, why, and how to fix it; keeps what the user typed; offers a retry.
   One failed part never blanks the page.
4. Reversible actions happen at once and offer Undo; confirm only irreversible, costly or batch
   actions.
5. Fields validate on blur and on submit, never on each keystroke. Errors sit below the field,
   linked with `aria-describedby`.
6. Status changes are announced with `aria-live="polite"`.

Markup patterns for these states are in ui-foundations' recipes, provisional until the system
ships the components (12).

## 9. Charts

1. Focus by default: one series in `accent-strong`, the rest `chart-muted`.
2. Categorical palette, fixed order, five maximum:

| Series | Light | Dark |
|---|---|---|
| 1 | cyan 10 | cyan 9 |
| 2 | violet 9 | violet 9 |
| 3 | crimson 9 | crimson 9 |
| 4 | yellow 11 | yellow 9 |
| 5 | mint 11 | mint 9 |

   Tested for the three main types of color blindness: the closest pair keeps a difference of 15 or more; at six series it drops to 11.
3. Never mix categorical and status colors in one chart.
4. 2px gaps between touching shapes; direct labels instead of legends; dashes or markers on lines when there are more than two.
5. Gridlines `border-subtle`, axis text `fg-muted`, baseline `border-strong`.
6. Heatmaps: accent steps 3 to 11. Diverging data: blue to orange.

## 10. Chinese and Japanese

1. Set `lang` on every element: `zh-Hans`, `zh-Hant` or `ja`.
2. Font stack: the Latin font first, then the language's font.
3. Trim to the text box: `text-box: trim-both text`.
4. Body line height 1.75 (28px at 360, 32px at 1440), lead 1.6, headings 1.3.
5. No negative letter-spacing, no italics.
6. Headings break between phrases, never inside one. Japanese: `word-break: auto-phrase`.
   Chinese: `word-break: keep-all` with `overflow-wrap: anywhere` (browsers have no Chinese
   phrase breaking), and the author marks each allowed break with `<wbr>`, after punctuation
   too: `慢慢来，<wbr>每一天<wbr>都是新的开始`. Without `<wbr>`, a Chinese heading breaks only
   where it would otherwise overflow. Body text breaks normally.
7. Subset or slice CJK fonts; full files are several megabytes per weight.

## 11. Accessibility

1. Contrast as in 3.5, checked across every accent and neutral in both modes.
2. Visible focus on keyboard; a skip link as the first stop.
3. Windows high contrast: selected, checked and disabled states switch to system colors.
4. Reflow: nothing scrolls sideways at 320px except data tables in their own scroll area.
5. Text spacing: raising line height to 1.5 and letter-spacing to 0.12em clips nothing.
6. Touch targets as in 8.4.
7. Reduced motion: every animation will respect it (see 12).
8. Composite widgets (tabs, list boxes, menus) use arrow keys and roving focus.

## 12. Not in v1

- Motion: durations, easing, reduced-motion rules.
- Icon set: custom-drawn, to replace the placeholders.
- Components: menu, dialog, popover, toast, data table, tag, progress, with keyboard behavior.
- Stacking order (`--z-*`): placeholder values until tested on those components.
- A scoped dark theme (one dark region inside a light page using the dark scales). The stage roles cover media until then.

## 13. Copy

1. Errors say what happened, why, and how to fix it, in plain words. No codes on their own, no
   blame, no humor.
2. Button labels are a verb plus an object ("Save changes", "Delete 3 reports"), never OK,
   Submit, Yes or No. Delete means gone for good, and gives the count; Remove means taken out of
   a list.
3. Loading text says what's loading ("Saving draft"). Empty states say what will appear.
4. One term per thing across the product. Sentence case everywhere; no period on labels or
   buttons.
5. Every field has a visible label; placeholders show format only. Link text makes sense on
   its own.
6. Strings are whole sentences, so they translate. Dates, numbers and currency use `Intl` for
   the page's `lang`.

## 14. Character

Sections 1 to 13 keep a page from looking wrong. They don't make it look like anything, and a page that only follows them comes out clean but anonymous: white, gray, thin lines, small text. Character comes from four dials and six composition rules. Each project chooses its dials in DESIGN.md, writes one sentence on how it should feel, and names its signature element.

### 14.1 Dials

Set on `<html>` like the accent: `<html data-surface="layered" data-shape="soft" data-type="loud" data-color="signature">`. With no dial set, a page looks as it did in v1.1.0.

| Dial | Values | What changes |
|---|---|---|
| `data-surface` | `lines` (default) | White canvas, hairline panels, outlined fields |
| | `fills` | Canvas neutral 2, borderless white surfaces, filled fields with no outline (3.5 rule 6) |
| | `layered` | Fills, plus cards raised with `--shadow-raised` |
| `data-shape` | `sharp` (default) / `soft` / `round` | Control, container and media radius (8.2) |
| `data-type` | `quiet` (default) / `loud` | The key element (`.p-key`) at title size or display size |
| `data-color` | `quiet` (default) | Accent for interaction and selection only |
| | `signature` | The accent may also fill one surface per view: the stage by default, or a header band. Muted text on it mixes toward the surface, never an accent step, so it doesn't read as a link |

1. Every dial works in light and dark, and component code never changes between dial values.
2. Choose for the project, not the page. A tool someone works in all day leans lines or fills and quiet type; a gallery, a launch page or a creative tool can go loud and signature.
3. Dial values are tokens. Never hand-set a radius, a shadow or a canvas tint to imitate one.

### 14.2 Composition

These apply whatever the dials are set to.

1. **Content first.** The view's main object (the image, the result, the document) takes the most space and comes first in reading order. Controls serve it beside or below.
2. **Fewer boxes.** Use the control that draws the least: a segmented control for 2 to 4 fixed options, a switch for on or off, a stepper for a small number, plain text for facts the user doesn't edit. Options most people never touch go behind a disclosure. A value that isn't edited is never drawn as a field.
3. **One key element.** Each view has one real number or title it's about (a total, a price, an estimate), set in `.p-key`. Never filler.
4. **One raised surface.** At most one card per view is raised; the rest group by spacing or fill. Raising everything raises nothing.
5. **The stage.** Media sits on `.stage`, which stays dark in both themes, so images read the same in light and dark mode. Its text uses the stage roles. Under the signature color, the stage is the accent surface.
6. **A signature.** Every project names one element someone would remember (for a video tool, the stage with its strip of takes). The review checks it's there (references/review.md, character check).
