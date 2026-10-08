// Design system lint rules (stylelint plugin). Three rules:
//   ds/token-values      spacing, radius, font size, weight and shadow come from tokens
//   ds/no-scale-steps    components use semantic roles (--fg-muted), never scale steps (--slate-9)
//   ds/font-size-count   at most N distinct font sizes per component (default 3)
// Every rule can be overridden for a special case, with a reason after "--":
//   /* stylelint-disable-next-line ds/font-size-count -- type specimen shows every role */
// A disable without a reason fails the run (reportDescriptionlessDisables in the config).
import stylelint from 'stylelint';
import valueParser from 'postcss-value-parser';
import path from 'node:path';

const { createPlugin, utils: { report, ruleMessages } } = stylelint;
const meta = {};

// ---------- ds/token-values ----------

const tokenValues = 'ds/token-values';
const tokenMessages = ruleMessages(tokenValues, {
  length: (prop, value) => `${prop}: raw "${value}". Use a token (${hint(prop)})`,
  weight: (value) => `font-weight: raw "${value}". Use --weight-regular, --weight-medium or --weight-strong`,
  shadow: () => 'box-shadow: hand-set shadow. Use --shadow-raised, --shadow-overlay or --shadow-modal',
});
const groups = [
  { test: /^(margin|padding)(-.+)?$|^(row-|column-)?gap$|^inset(-.+)?$|^(top|right|bottom|left)$/, hint: '--space-1 to --space-10' },
  { test: /^border(-.+)?-radius$/, hint: '--radius-0 to --radius-4, --radius-full' },
  { test: /^font-size$/, hint: 'a --text-* role' },
];
const groupOf = (prop) => groups.find((g) => g.test.test(prop));
const hint = (prop) => groupOf(prop)?.hint ?? 'a token';
const keywords = new Set(['inherit', 'initial', 'unset', 'revert', 'revert-layer']);

// Raw lengths in a value, skipping var() so fallbacks don't count. Zero, % and ±1px hairlines
// (overhangs, sr-only) are allowed.
const hairline = (n) => n.unit === 'px' && Math.abs(parseFloat(n.number)) === 1;
function rawLengths(value) {
  const found = [];
  valueParser(value).walk((node) => {
    if (node.type === 'function' && node.value === 'var') return false;
    if (node.type !== 'word') return;
    const n = valueParser.unit(node.value);
    if (n && n.unit && n.unit !== '%' && parseFloat(n.number) !== 0 && !hairline(n)) found.push(node.value);
  });
  return found;
}
const onlyVars = (value) => valueParser(value).nodes.every((n) =>
  (n.type === 'function' && n.value === 'var') || n.type === 'space' || n.type === 'div');
// An inset shadow with no length past 1px is a hairline border drawn inside the box, not elevation.
const insetHairlines = (value) => valueParser(value).nodes
  .reduce((layers, n) => { if (n.type === 'div' && n.value === ',') layers.push([]); else layers.at(-1).push(n); return layers; }, [[]])
  .every((layer) => layer.some((n) => n.type === 'word' && n.value === 'inset') && rawLengths(valueParser.stringify(layer)).length === 0);

const tokenRule = (on) => (root, result) => {
  if (!on) return;
  root.walkDecls((decl) => {
    const prop = decl.prop.toLowerCase(), value = decl.value.trim();
    if (keywords.has(value)) return;
    if (prop === 'font-weight') {
      if (!/^var\(/.test(value) && value !== 'normal') report({ ruleName: tokenValues, result, node: decl, message: tokenMessages.weight(value), word: value });
      return;
    }
    if (prop === 'box-shadow') {
      if (value !== 'none' && !onlyVars(value) && !insetHairlines(value)) report({ ruleName: tokenValues, result, node: decl, message: tokenMessages.shadow(), word: value });
      return;
    }
    if (!groupOf(prop)) return;
    for (const raw of rawLengths(value)) report({ ruleName: tokenValues, result, node: decl, message: tokenMessages.length(prop, raw), word: raw });
  });
};
tokenRule.ruleName = tokenValues; tokenRule.messages = tokenMessages; tokenRule.meta = meta;

// ---------- ds/no-scale-steps ----------

const scaleSteps = 'ds/no-scale-steps';
const scaleMessages = ruleMessages(scaleSteps, {
  step: (name) => `${name} is a scale step. Use a semantic role (--fg-muted, --bg-subtle, --accent-solid...)`,
});
const scales = 'accent|neutral|amber|blue|bronze|brown|crimson|cyan|gold|grass|gray|green|indigo|iris|jade|lime|mauve|mint|olive|orange|pink|plum|purple|red|ruby|sage|sand|sky|slate|teal|tomato|violet|yellow';
const stepRe = new RegExp(`var\\(\\s*(--(?:${scales})-a?\\d{1,2})\\b`, 'g');

const scaleRule = (on) => (root, result) => {
  if (!on) return;
  root.walkDecls((decl) => {
    if (decl.prop.startsWith('--')) return; // defining a role from a step is what token files do
    for (const m of decl.value.matchAll(stepRe)) report({ ruleName: scaleSteps, result, node: decl, message: scaleMessages.step(m[1]), word: m[1] });
  });
};
scaleRule.ruleName = scaleSteps; scaleRule.messages = scaleMessages; scaleRule.meta = meta;

// ---------- ds/font-size-count ----------
// A component is a whole file for single-component formats (.astro, .vue, .svelte, *.module.css).
// In any other stylesheet it is the first class in each selector: `.cs-toc .mk` belongs to
// .cs-toc. Rules with no class (h1, :root) are base styles, not a component, and aren't counted.

const sizeCount = 'ds/font-size-count';
const sizeMessages = ruleMessages(sizeCount, {
  over: (comp, n, max, sizes) => `${comp} has ${n} font sizes (max ${max}): ${sizes.join(', ')}. ` +
    `Merge to ${max} or fewer, or keep it with: stylelint-disable-next-line ${sizeCount} -- <reason>`,
});
const wholeFile = /\.(astro|vue|svelte)$|\.module\.css$/;
const firstClass = (selector) => selector.match(/\.(-?[_a-zA-Z][\w-]*)/)?.[1];

const sizeRule = (max = 3) => (root, result) => {
  const file = root.source?.input?.file ?? '';
  const byComp = new Map(); // component -> Set of sizes
  root.walkDecls('font-size', (decl) => {
    const rule = decl.parent;
    if (rule?.type !== 'rule') return;
    const size = decl.value.trim().replace(/\s+/g, ' ').toLowerCase();
    const comps = wholeFile.test(file)
      ? [path.basename(file)]
      : [...new Set(rule.selectors.map(firstClass).filter(Boolean).map((c) => `.${c}`))];
    for (const comp of comps) {
      if (!byComp.has(comp)) byComp.set(comp, new Set());
      const sizes = byComp.get(comp);
      if (sizes.has(size)) continue;
      sizes.add(size);
      if (sizes.size > max) report({ ruleName: sizeCount, result, node: decl, message: sizeMessages.over(comp, sizes.size, max, [...sizes]), word: decl.value });
    }
  });
};
sizeRule.ruleName = sizeCount; sizeRule.messages = sizeMessages; sizeRule.meta = meta;

export default [
  createPlugin(tokenValues, tokenRule),
  createPlugin(scaleSteps, scaleRule),
  createPlugin(sizeCount, sizeRule),
];
