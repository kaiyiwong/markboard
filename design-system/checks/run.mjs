// Design system checks. Usage: npm run check  (needs: npm i -D playwright && npx playwright install chromium)
import { chromium } from 'playwright';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';
import { readFileSync } from 'node:fs';

const here = path.dirname(fileURLToPath(import.meta.url));
const url = pathToFileURL(path.join(here, '../specimen/index.html')).href;
const problems = [];
const palettes = readFileSync(path.join(here, '../palettes.css'), 'utf8');
const listOf = (attr) => [...new Set([...palettes.matchAll(new RegExp(`\\[data-${attr}="([a-z]+)"\\]`, 'g'))].map((m) => m[1]))];
const accents = listOf('accent'), neutrals = listOf('neutral');
const log = (ok, msg) => { console.log(`${ok ? 'pass' : 'FAIL'}  ${msg}`); if (!ok) problems.push(msg); };

const browser = await chromium.launch();
const open = async (opts) => { const p = await browser.newPage(opts); await p.goto(url); await p.waitForTimeout(400); return p; };

// 1. Contrast: every accent and neutral, light and dark
for (const scheme of ['light', 'dark']) {
  const page = await open({ viewport: { width: 1440, height: 900 }, colorScheme: scheme });
  const fails = await page.evaluate(([accents, neutrals]) => {
    const out = [];
    for (const a of accents) for (const n of neutrals) {
      document.documentElement.dataset.accent = a; document.documentElement.dataset.neutral = n;
      window.__specimenUpdate();
      document.querySelectorAll('.cr.fail').forEach((e) => out.push(`${a}/${n}: ${e.parentElement.querySelector('.mono-s').textContent} ${e.textContent}`));
    }
    return out;
  }, [accents, neutrals]);
  log(fails.length === 0 && accents.length > 0, `contrast, ${accents.length} accents x ${neutrals.length} neutrals, ${scheme}${fails.length ? ': ' + fails.slice(0, 5).join('; ') : ''}`);
  await page.close();
}

// 2. Reflow and longest word
for (const width of [320, 375, 768, 1024, 1440]) {
  const page = await open({ viewport: { width, height: 900 } });
  const r = await page.evaluate(() => ({
    sideways: document.documentElement.scrollWidth > innerWidth,
    words: [...document.querySelectorAll('.role .s .t')].filter((e) => e.scrollWidth > e.clientWidth + 1).map((e) => e.id),
  }));
  log(!r.sideways, `no sideways scroll at ${width}px`);
  log(r.words.length === 0, `longest words fit at ${width}px${r.words.length ? ': ' + r.words.join(', ') : ''}`);
  await page.close();
}

// 3. Images and components sit on the page grid
for (const width of [375, 800, 1440]) {
  const page = await open({ viewport: { width, height: 900 } });
  await page.click('[data-overlay="show-grid"]');
  const off = await page.evaluate(() => {
    const edges = [...document.querySelectorAll('.grid-overlay .grid>div')].filter((d) => getComputedStyle(d).display !== 'none')
      .flatMap((d) => { const r = d.getBoundingClientRect(); return [r.left, r.right]; });
    const near = (x) => edges.some((e) => Math.abs(e - x) <= 1);
    return [...document.querySelectorAll('.fig img, .report-wrap')].filter((el) => { const r = el.getBoundingClientRect(); return !(near(r.left) && near(r.right)); }).length;
  });
  log(off === 0, `images and containers on grid lines at ${width}px`);
  await page.close();
}

// 4. WCAG 1.4.12 text spacing: no label escapes its control
{
  const page = await open({ viewport: { width: 1440, height: 900 } });
  await page.addStyleTag({ content: '*{line-height:1.5!important;letter-spacing:.12em!important;word-spacing:.16em!important}p{margin-bottom:2em!important}' });
  const esc = await page.evaluate(() => [...document.querySelectorAll('.btn,.input,.tab,.option,.choice,.switch-row')].filter((e) => {
    const c = e.getBoundingClientRect();
    return [...e.querySelectorAll('.lbl')].some((k) => { const a = k.getBoundingClientRect(); return a.top < c.top - 0.5 || a.bottom > c.bottom + 0.5 || a.left < c.left - 0.5 || a.right > c.right + 0.5; });
  }).length);
  log(esc === 0, 'text spacing override clips no labels');
  await page.close();
}

// 5. Chinese headings break only at <wbr> (SPEC 10.6)
for (const width of [320, 360, 375]) {
  const page = await open({ viewport: { width, height: 900 } });
  const bad = await page.evaluate(() => {
    const h = document.getElementById('zh-phrases');
    if (!h) return ['#zh-phrases missing'];
    // Character offsets where a <wbr> allows a break, counted over the heading's text.
    const allowed = new Set(); let text = '';
    for (const n of h.childNodes) { if (n.nodeName === 'WBR') allowed.add(text.length); else text += n.textContent; }
    // Walk the characters; a new line starts where a character's top jumps down.
    const out = []; let offset = 0, lastTop = null;
    for (const n of h.childNodes) {
      if (n.nodeType !== 3) continue;
      for (let i = 0; i < n.length; i++) {
        const r = document.createRange(); r.setStart(n, i); r.setEnd(n, i + 1);
        const top = r.getBoundingClientRect().top;
        if (lastTop !== null && top > lastTop + 4 && !allowed.has(offset + i)) out.push(`break before "${text[offset + i]}" at ${offset + i}`);
        lastTop = top;
      }
      offset += n.length;
    }
    return out;
  });
  log(bad.length === 0, `Chinese heading breaks only at <wbr> at ${width}px${bad.length ? ': ' + bad.join('; ') : ''}`);
  await page.close();
}

await browser.close();
console.log(problems.length ? `\n${problems.length} problem(s)` : '\nall checks passed');
process.exit(problems.length ? 1 : 0);
