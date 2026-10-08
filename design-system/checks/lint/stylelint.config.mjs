// Design system lint. Raw values fail; tokens pass. Token files are where raw values live,
// so they're skipped. Override a rule for a special case with a reason:
//   /* stylelint-disable-next-line ds/font-size-count -- type specimen shows every role */
// A disable with no reason, or one that no longer disables anything, fails the run.
import path from 'node:path';

// ignoreFiles resolve from this file's folder, so anchor them to the project the lint runs in,
// by both its real path and the path it was reached by (they differ under a symlink).
const roots = [...new Set([process.cwd(), process.env.PWD].filter(Boolean))];
const project = (glob) => roots.map((root) => path.join(root, glob));

export default {
  plugins: ['./ds.mjs'],
  overrides: [{ files: ['**/*.astro', '**/*.html', '**/*.vue', '**/*.svelte'], customSyntax: 'postcss-html' }],
  ignoreFiles: ['**/design-system/**', '**/tokens.css', '**/node_modules/**', '**/dist/**'].flatMap(project),
  reportDescriptionlessDisables: true,
  reportNeedlessDisables: true,
  reportInvalidScopeDisables: true,
  rules: {
    // Colors come from role tokens
    'color-no-hex': true,
    'color-named': ['never', { ignoreProperties: [/^(-webkit-)?mask/] }], // mask colours are alpha only, never shown
    'function-disallowed-list': ['rgb', 'rgba', 'hsl', 'hsla', 'hwb', 'lab', 'lch', 'oklab', 'oklch', 'color'],
    'ds/no-scale-steps': true,
    // Spacing, radius, type size, weight and shadow come from tokens
    'ds/token-values': true,
    'ds/font-size-count': 3,
  },
};
