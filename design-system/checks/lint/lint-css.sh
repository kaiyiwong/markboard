#!/bin/bash
# PostToolUse hook (Edit|Write|MultiEdit): lint a stylesheet right after an agent edits it.
# Exit 2 sends the errors back to Claude so it fixes them before moving on. Does nothing in a
# project without the design lint installed, or for files that aren't stylesheets.
# Install: see design-system/README.md, "Lint".
f=$(jq -r '.tool_input.file_path // empty')
case "$f" in *.css|*.astro|*.vue|*.svelte|*.html) ;; *) exit 0 ;; esac
cd "${CLAUDE_PROJECT_DIR:-.}" || exit 0
config=design-system/checks/lint/stylelint.config.mjs
[ -x node_modules/.bin/stylelint ] && [ -f "$config" ] || exit 0
out=$(node_modules/.bin/stylelint "$f" --config "$config" --allow-empty-input -f unix 2>&1) && exit 0
echo "Design lint failed on $f. Fix the value, or keep it with a reason (stylelint-disable-next-line <rule> -- <why>):" >&2
echo "$out" >&2
exit 2
