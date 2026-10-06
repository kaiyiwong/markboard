(function () {
  const root = document.documentElement;
  const $ = (s) => document.querySelector(s);

  /* ---------- theme, neutral, accent, overlays ---------- */
  document.querySelectorAll('[data-theme-set]').forEach((b) => {
    b.addEventListener('click', () => {
      const v = b.dataset.themeSet;
      root.classList.remove('light', 'dark');
      if (v !== 'system') root.classList.add(v);
      document.querySelectorAll('[data-theme-set]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
      update();
    });
  });
  document.querySelectorAll('[data-neutral-set]').forEach((b) => b.addEventListener('click', () => {
    root.dataset.neutral = b.dataset.neutralSet;
    document.querySelectorAll('[data-neutral-set]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
    update();
  }));
  document.querySelectorAll('[data-accent-set]').forEach((b) => b.addEventListener('click', () => {
    root.dataset.accent = b.dataset.accentSet;
    document.querySelectorAll('[data-accent-set]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
    update();
  }));
  document.querySelectorAll('[data-voice-set]').forEach((b) => b.addEventListener('click', () => {
    if (b.dataset.voiceSet === 'none') delete root.dataset.voice; else root.dataset.voice = b.dataset.voiceSet;
    document.querySelectorAll('[data-voice-set]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
    update();
    if (document.fonts) document.fonts.ready.then(update);
  }));
  document.querySelectorAll('[data-dial-set]').forEach((b) => b.addEventListener('click', () => {
    const attr = 'data-' + b.dataset.dialSet;
    root.setAttribute(attr, b.dataset.v);
    document.querySelectorAll(`[data-dial-set="${b.dataset.dialSet}"]`).forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
    update();
  }));
  document.querySelectorAll('[data-toggle-switch]').forEach((b) => b.addEventListener('click', (e) => {
    e.preventDefault(); b.setAttribute('aria-checked', String(b.getAttribute('aria-checked') !== 'true'));
  }));
  document.querySelectorAll('[data-overlay]').forEach((b) => b.addEventListener('click', () => {
    const on = b.getAttribute('aria-pressed') !== 'true';
    b.setAttribute('aria-pressed', String(on)); root.classList.toggle(b.dataset.overlay, on);
  }));
  document.querySelectorAll('[data-toggle]').forEach((b) => b.addEventListener('click', () => {
    b.setAttribute('aria-pressed', String(b.getAttribute('aria-pressed') !== 'true'));
  }));
  document.querySelectorAll('[role="tab"]').forEach((t) => t.addEventListener('click', () => {
    t.parentElement.querySelectorAll('[role="tab"]').forEach((x) => x.setAttribute('aria-selected', String(x === t)));
  }));
  document.querySelectorAll('[role="option"]').forEach((o) => o.addEventListener('click', () => {
    o.parentElement.querySelectorAll('[role="option"]').forEach((x) => x.setAttribute('aria-selected', String(x === o)));
  }));

  document.querySelectorAll('[role="switch"]').forEach((s) => s.addEventListener('click', () => {
    s.setAttribute('aria-checked', String(s.getAttribute('aria-checked') !== 'true'));
  }));

  /* ---------- ladder ---------- */
  const uses = {
    1: 'Icon to its label', 2: 'Inside controls, tight clusters', 3: 'Control padding, touch control spacing',
    4: 'Small card padding, form rows', 5: 'Default card padding, gaps between cards', 6: 'Panel padding, between form groups',
    7: 'Between blocks inside a section', 8: 'Between sub-sections', 9: 'Between sections', 10: 'Top and bottom of the page or hero'
  };
  const lad = $('#ladder');
  for (let i = 1; i <= 10; i++) {
    const r = document.createElement('div');
    r.className = 'lad';
    r.innerHTML = `<span class="mono-s">space-${i}</span><b class="num" id="sp-${i}">0</b><div class="b" style="width:var(--space-${i})"></div><span class="use">${uses[i]}</span>`;
    lad.appendChild(r);
  }

  /* ---------- swatches ---------- */
  const groups = [
    ['Backgrounds', [['bg-canvas', 0], ['bg-subtle', 0], ['bg-surface', 0], ['bg-control', 0], ['bg-control-hover', 0], ['bg-control-active', 0]]],
    ['Borders', [['border-subtle', 0], ['border-default', 0], ['border-strong', 0], ['border-control', 3]]],
    ['Text', [['fg-default', 4.5], ['fg-muted', 4.5], ['fg-disabled', 0]]],
    ['Accent', [['accent-bg', 0], ['accent-solid', 0], ['accent-solid-hover', 0], ['accent-fg', 4.5], ['accent-strong', 3], ['focus-ring', 3]]],
    ['Status', [['danger-border', 3], ['danger-fg', 4.5], ['warning-fg', 4.5], ['success-border', 3], ['success-fg', 4.5], ['info-fg', 4.5]]],
    ['Charts', [['chart-1', 3], ['chart-2', 3], ['chart-3', 3], ['chart-4', 3], ['chart-5', 3], ['chart-muted', 3]]]
  ];
  const pairs = [
    ['Primary label', 'fg-on-accent', 'accent-solid', 4.5], ['Primary label, hover', 'fg-on-accent', 'accent-solid-hover', 4.5],
    ['Primary label, pressed', 'fg-on-accent', 'accent-solid-active', 4.5], ['Secondary label', 'fg-default', 'bg-control', 4.5],
    ['Selected tab', 'accent-fg', 'bg-canvas', 4.5], ['Selected option', 'accent-fg-strong', 'accent-bg', 4.5],
    ['Check mark', '#ffffff', 'accent-strong', 3], ['Destructive label', '#ffffff', 'danger-solid', 4.5],
    ['Destructive label, hover', '#ffffff', 'danger-solid-hover', 4.5], ['Switch thumb, on', '#ffffff', 'accent-strong', 3]
  ];
  const sw = $('#swatches');
  groups.forEach(([name, list]) => {
    const g = document.createElement('div');
    g.className = 'sw-group';
    g.innerHTML = `<p class="t t-label">${name}</p>`;
    list.forEach(([tok, target]) => {
      const r = document.createElement('div');
      r.className = 'sw';
      r.innerHTML = `<span class="chip" style="background:var(--${tok})" data-tok="${tok}" data-target="${target}"></span><span class="mono-s">${tok}</span><span class="val"></span><span class="cr"></span>`;
      g.appendChild(r);
    });
    sw.appendChild(g);
  });
  const pg = document.createElement('div');
  pg.className = 'sw-group';
  pg.innerHTML = '<p class="t t-label">Pairs</p>';
  pairs.forEach(([name, fg, bg, target]) => {
    const r = document.createElement('div');
    r.className = 'sw';
    const fgv = fg.startsWith('#') ? fg : `var(--${fg})`;
    r.innerHTML = `<span class="pair" aria-hidden="true" style="color:${fgv};background:var(--${bg})" data-target="${target}">Aa</span><span class="mono-s">${name}</span><span class="val"></span><span class="cr"></span>`;
    pg.appendChild(r);
  });
  sw.insertBefore(pg, sw.children[3]);

  /* ---------- color maths (canvas converts P3 to sRGB) ---------- */
  const cv = document.createElement('canvas'); cv.width = cv.height = 1;
  const cx = cv.getContext('2d', { willReadFrequently: true });
  function rgbOf(str) {
    cx.clearRect(0, 0, 1, 1); cx.fillStyle = '#000'; cx.fillStyle = str; cx.fillRect(0, 0, 1, 1);
    const d = cx.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2]];
  }
  const lin = (c) => { c /= 255; return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); };
  const lum = (rgb) => 0.2126 * lin(rgb[0]) + 0.7152 * lin(rgb[1]) + 0.0722 * lin(rgb[2]);
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  const hex = (rgb) => '#' + rgb.map((v) => v.toString(16).padStart(2, '0')).join('');

  /* ---------- readouts ---------- */
  const px = (v) => `${Math.round(v * 10) / 10}px`;
  function update() {
    const cs = getComputedStyle(document.body);
    const w = window.innerWidth;
    $('#ro-w').textContent = `${w}px`;
    $('#ro-body').textContent = px(parseFloat(cs.fontSize));
    const u = parseFloat(cs.getPropertyValue('--u'));
    $('#ro-u').textContent = px(u);
    $('#ro-pair').textContent = px(u); $('#ro-para').textContent = px(u * 2); $('#ro-group').textContent = px(u * 3);
    const rs = getComputedStyle(root);
    const cols = parseInt(rs.getPropertyValue('--cols'), 10);
    $('#ro-cols').textContent = cols;
    $('#ro-gutter').textContent = rs.getPropertyValue('--gutter').trim();
    $('#ro-margin').textContent = rs.getPropertyValue('--margin').trim();
    const strip = document.querySelector('.cols-strip > div');
    if (strip) $('#ro-colw').textContent = px(strip.getBoundingClientRect().width);

    document.querySelectorAll('[data-metrics]').forEach((m) => {
      const el = document.getElementById(m.dataset.metrics); const s = getComputedStyle(el);
      const fs = parseFloat(s.fontSize), lh = parseFloat(s.lineHeight), ls = parseFloat(s.letterSpacing) || 0;
      const em = Math.round((ls / fs) * 1000) / 1000;
      const lsTxt = (em === 0 ? '0' : em.toString().replace('-', '\u2212')) + 'em';
      m.textContent = `${px(fs)} / ${px(lh)} / ${lsTxt}`;
    });
    for (let i = 1; i <= 10; i++) {
      const b = document.querySelector(`#ladder .lad:nth-child(${i}) .b`);
      $(`#sp-${i}`).textContent = px(b.getBoundingClientRect().width);
    }
    document.querySelectorAll('[data-measure]').forEach((m) => {
      const el = document.getElementById(m.dataset.measure); const r = el.getBoundingClientRect(); const s = getComputedStyle(el);
      const pl = Math.round(parseFloat(s.paddingInlineStart)), pr = Math.round(parseFloat(s.paddingInlineEnd));
      m.textContent = `${Math.round(r.width)} \u00d7 ${Math.round(r.height)}, padding ${pl} left, ${pr} right`;
    });
    document.querySelectorAll('[data-space]').forEach((c) => { c.textContent = $(`#sp-${c.dataset.space}`).textContent; });
    document.querySelectorAll('[data-u]').forEach((c) => { c.textContent = c.dataset.u.split(',').map((n) => px(u * parseFloat(n))).join(', '); });
    document.querySelectorAll('[data-cqw]').forEach((m) => { m.textContent = px(document.getElementById(m.dataset.cqw).getBoundingClientRect().width); });
    const canvasRGB = rgbOf(getComputedStyle(document.body).backgroundColor);
    document.querySelectorAll('.pair').forEach((c) => {
      const s = getComputedStyle(c), fg = rgbOf(s.color), bg = rgbOf(s.backgroundColor);
      const row = c.parentElement, t = parseFloat(c.dataset.target), r = ratio(fg, bg), crEl = row.querySelector('.cr');
      row.querySelector('.val').textContent = hex(bg);
      const shown = (Math.floor(r * 100) / 100).toFixed(2) + ':1';
      crEl.textContent = r >= t ? `${shown} ok` : `${shown} below ${t}:1`; crEl.classList.toggle('fail', r < t);
    });
    document.querySelectorAll('.chip').forEach((c) => {
      const rgb = rgbOf(getComputedStyle(c).backgroundColor);
      const row = c.parentElement, t = parseFloat(c.dataset.target);
      row.querySelector('.val').textContent = hex(rgb);
      const r = ratio(rgb, canvasRGB), crEl = row.querySelector('.cr');
      const shown = (Math.floor(r * 100) / 100).toFixed(2) + ':1';
      if (t > 0) { crEl.textContent = r >= t ? `${shown} ok` : `${shown} below ${t}:1`; crEl.classList.toggle('fail', r < t); }
      else { crEl.textContent = shown; crEl.classList.remove('fail'); }
    });
  }

  let raf = 0;
  const schedule = () => { cancelAnimationFrame(raf); raf = requestAnimationFrame(update); };
  window.addEventListener('resize', schedule);
  matchMedia('(prefers-color-scheme: dark)').addEventListener('change', schedule);
  new MutationObserver(schedule).observe(root, { attributes: true, attributeFilter: ['data-theme', 'class'] });
  window.__specimenUpdate = update;
  update();
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(update);
  if (document.fonts) document.fonts.addEventListener('loadingdone', schedule);
})();
