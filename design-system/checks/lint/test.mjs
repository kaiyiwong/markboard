// Tests the design lint on its fixtures: every rule fires on bad input, clean input passes, and
// an override passes only with a reason. Usage: npm run check:lint
import stylelint from 'stylelint';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
const configFile = path.join(here, 'stylelint.config.mjs');
const expected = {
  'bad.css': ['color-no-hex', 'color-named', 'function-disallowed-list', 'ds/no-scale-steps', 'ds/token-values', 'ds/font-size-count'],
  'Bad.astro': ['color-no-hex', 'ds/token-values', 'ds/font-size-count'],
  'good.css': [],
  'override-ok.css': [],
  'override-no-reason.css': ['--report-descriptionless-disables'],
};

let failed = 0;
for (const [file, rules] of Object.entries(expected)) {
  const { results: [r] } = await stylelint.lint({ files: path.join(here, 'fixtures', file), configFile });
  const got = [...new Set(r.warnings.map((w) => w.rule))].sort();
  const ok = got.join() === [...rules].sort().join();
  console.log(`${ok ? 'pass' : 'FAIL'}  ${file}: ${got.length ? got.join(', ') : 'clean'}`);
  if (!ok) { failed++; console.log(`      expected: ${rules.length ? rules.join(', ') : 'clean'}`); }
}
process.exit(failed ? 1 : 0);
