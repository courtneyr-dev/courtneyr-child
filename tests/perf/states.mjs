// usage: node states.mjs <labels-and-urls.txt> <out.ndjson> <repo-assets-css-dir> [only-label,...]
// A/B on the SAME dev page: 'src' = theme stylesheets served from the repo source (and inline theme sheets rewritten to the source),
// 'min' = dev as served (minified), 'src2' = noise control. Per load: print-media computed styles of every element, forced
// :hover/:focus/:focus-visible/:active computed styles of up to 30 interactive elements, and (1280 dark) 10 keyboard Tab focus rings.
import { spawn } from 'node:child_process';
import fs from 'node:fs'; import path from 'node:path'; import os from 'node:os';

const [urlFile, outFile, cssDir, only] = process.argv.slice(2);
let pages = fs.readFileSync(urlFile, 'utf8').trim().split('\n').map(l => l.split(' '));
if (only) { const s = new Set(only.split(',')); pages = pages.filter(([l]) => s.has(l)); }
const COMBOS = [{ w: 390, scheme: 'light', tab: false }, { w: 1280, scheme: 'dark', tab: true }];
const VARIANTS = ['src', 'min', 'src2'];
const ORIGIN = new URL(pages[0][1]).origin; // the pages file decides the site, so one script serves dev, test and live
const CSS_PREFIX = '/wp-content/themes/courtneyr-child/assets/css/';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const port = 9400 + Math.floor(Math.random() * 400);
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'states-profile-'));
const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  [`--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--disable-extensions', '--disable-sync', '--window-position=-2400,0', '--window-size=500,900', 'about:blank'], { stdio: 'ignore' });
let ver;
for (let i = 0; i < 50; i++) { try { ver = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json(); break; } catch { await sleep(200); } }
if (!ver) { chrome.kill(); throw new Error('chrome did not start'); }
const ws = new WebSocket(ver.webSocketDebuggerUrl);
await new Promise(r => ws.addEventListener('open', r));
let id = 0; const pending = {}; const handlers = [];
ws.addEventListener('message', m => { const d = JSON.parse(m.data); if (d.id && pending[d.id]) { pending[d.id](d); delete pending[d.id]; } else handlers.forEach(h => h(d)); });
const send = (method, params = {}, sessionId) => new Promise((res, rej) => { const i = ++id; pending[i] = d => d.error ? rej(new Error(method + ': ' + d.error.message)) : res(d.result); ws.send(JSON.stringify({ id: i, method, params, sessionId })); });

// handle -> source file, for rewriting inline theme sheets in the 'src' variants
const walk = d => fs.readdirSync(d, { withFileTypes: true }).flatMap(e => e.isDirectory() ? walk(path.join(d, e.name)) : e.name.endsWith('.css') ? [path.join(d, e.name)] : []);
const HANDLE_FILE = {};
for (const f of walk(cssDir)) {
  const rel = path.relative(cssDir, f).split(path.sep).join('/');
  if (rel.startsWith('blocks/')) HANDLE_FILE['block-' + rel.slice(7, -4)] = rel;
  else if (rel.startsWith('cr-')) HANDLE_FILE[rel.slice(3, -4)] = rel;
}
const absUrls = (css, rel) => css.replace(/url\(\s*(['"]?)([^)'"]+)\1\s*\)/g, (m, q, u) => /^(data:|https?:|\/|#)/.test(u) ? m : `url(${q}${new URL(u, ORIGIN + CSS_PREFIX + rel).href}${q})`);
const INLINE_RX = /(<style\b[^>]*\bid=['"]courtneyr-([a-z0-9-]+?)(-inline)?-css['"][^>]*>)([\s\S]*?)(<\/style>)/g;

const SNAP = `(() => {
  const SKIP = new Set(['SCRIPT','STYLE','LINK','META','NOSCRIPT','TEMPLATE','HEAD','TITLE','BASE']);
  const cs0 = getComputedStyle(document.documentElement); const names = []; for (let i = 0; i < cs0.length; i++) if (!cs0[i].startsWith('--')) names.push(cs0[i]); names.sort();
  document.getAnimations().forEach(a => { try { a.pause(); a.currentTime = 0; } catch (e) {} });
  const dict = []; const seen = new Map(); const keys = {};
  const rvals = cs => names.map(n => cs.getPropertyValue(n));
  const visit = (el, pk, idx) => {
    if (SKIP.has(el.tagName)) return;
    const key = pk + '>' + el.tagName.toLowerCase() + (el.id ? '#' + el.id : ':' + idx);
    const cs = getComputedStyle(el); const o = { r: rvals(cs) };
    for (const p of ['::before', '::after']) { const pc = getComputedStyle(el, p); if (pc.content !== 'none' && pc.content !== 'normal') o[p] = rvals(pc); }
    const j = JSON.stringify(o); let n = seen.get(j); if (n === undefined) { n = dict.length; dict.push(j); seen.set(j, n); } keys[key] = n;
    let i = 0; for (const c of el.children) visit(c, key, i++);
  };
  visit(document.documentElement, '', 0);
  return { names, dict, keys };
})()`;

async function loadOnce(variant, url, combo) {
  const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
  const S = (m, p) => send(m, p, sessionId);
  const statuses = []; let docStatus = null;
  const h = async d => {
    if (d.sessionId !== sessionId) return; const p = d.params;
    if (d.method === 'Network.responseReceived') { statuses.push([p.response.status, p.type, p.response.url]); if (p.type === 'Document' && docStatus === null) docStatus = p.response.status; }
    if (d.method !== 'Fetch.requestPaused') return;
    const rid = p.requestId;
    try {
      if (p.responseStatusCode === undefined) {
        const u = new URL(p.request.url);
        if (variant === 'min' || !u.pathname.startsWith(CSS_PREFIX) || !u.pathname.endsWith('.css')) return await S('Fetch.continueRequest', { requestId: rid });
        const f = path.join(cssDir, decodeURIComponent(u.pathname.slice(CSS_PREFIX.length)));
        if (!fs.existsSync(f)) return await S('Fetch.continueRequest', { requestId: rid });
        await S('Fetch.fulfillRequest', { requestId: rid, responseCode: 200, responseHeaders: [{ name: 'Content-Type', value: 'text/css; charset=utf-8' }, { name: 'Cache-Control', value: 'no-store' }], body: fs.readFileSync(f).toString('base64') });
      } else {
        const ct = (p.responseHeaders || []).find(x => x.name.toLowerCase() === 'content-type')?.value || '';
        if (variant === 'min' || p.responseStatusCode !== 200 || !ct.includes('text/html')) return await S('Fetch.continueResponse', { requestId: rid });
        const rb = await S('Fetch.getResponseBody', { requestId: rid });
        let html = rb.base64Encoded ? Buffer.from(rb.body, 'base64').toString('utf8') : rb.body;
        html = html.replace(INLINE_RX, (m, open, handle, inl, css, close) => {
          if (handle === 'components' && inl) return m;            // inline additions, not the file
          const rel = HANDLE_FILE[handle]; if (!rel) return m;
          return open + '\n' + absUrls(fs.readFileSync(path.join(cssDir, rel), 'utf8'), rel) + '\n' + close;
        });
        const hdr = (p.responseHeaders || []).filter(x => !['content-encoding', 'content-length', 'transfer-encoding'].includes(x.name.toLowerCase()));
        await S('Fetch.fulfillRequest', { requestId: rid, responseCode: 200, responseHeaders: hdr, body: Buffer.from(html).toString('base64') });
      }
    } catch (e) { try { p.responseStatusCode === undefined ? await S('Fetch.continueRequest', { requestId: rid }) : await S('Fetch.continueResponse', { requestId: rid }); } catch {} }
  };
  handlers.push(h);
  try {
    await send('Target.activateTarget', { targetId });
    await S('Emulation.setFocusEmulationEnabled', { enabled: true });
    await S('Emulation.setDeviceMetricsOverride', { width: combo.w, height: combo.w < 800 ? 844 : 800, deviceScaleFactor: 1, mobile: combo.w < 800 });
    await S('Emulation.setEmulatedMedia', { media: '', features: [{ name: 'prefers-color-scheme', value: combo.scheme }] });
    for (const d of ['Page', 'Network', 'Runtime', 'DOM', 'CSS']) await S(d + '.enable');
    await S('Network.setCacheDisabled', { cacheDisabled: true });
    await S('Fetch.enable', { patterns: [{ urlPattern: ORIGIN + CSS_PREFIX + '*', requestStage: 'Request' }, { urlPattern: ORIGIN + '/*', resourceType: 'Document', requestStage: 'Response' }] });
    const loaded = new Promise(r => { const lh = d => { if (d.sessionId === sessionId && d.method === 'Page.loadEventFired') r(); }; handlers.push(lh); setTimeout(r, 40000); });
    await S('Page.navigate', { url });
    await loaded; await sleep(2500);
    const ev = async e => (await S('Runtime.evaluate', { expression: e, returnByValue: true, awaitPromise: true, timeout: 90000 })).result.value;
    const STABLE = `(async () => { try { await document.fonts.ready; } catch (e) {} let last = -1, since = Date.now(); const t0 = Date.now(); while (Date.now() - t0 < 12000) { const h = document.documentElement.scrollHeight + ':' + document.querySelectorAll('img:not([complete])').length; if (h !== last) { last = h; since = Date.now(); } else if (Date.now() - since > 1200) break; await new Promise(r => setTimeout(r, 200)); } return last; })()`;
    await ev(STABLE);
    await S('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 120, y: 260 }); await sleep(250);
    await S('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 200, y: 330 }); await sleep(2500);
    await ev(STABLE);
    await ev('if (document.activeElement && document.activeElement !== document.body) document.activeElement.blur(); 1');
    // 1. print media: computed styles of every element
    await S('Emulation.setEmulatedMedia', { media: 'print', features: [{ name: 'prefers-color-scheme', value: combo.scheme }] });
    await sleep(400);
    const print = await ev(SNAP);
    await S('Emulation.setEmulatedMedia', { media: '', features: [{ name: 'prefers-color-scheme', value: combo.scheme }] });
    await sleep(300);
    // 2. forced pseudo states on interactive elements (transitions and animations off equally in every variant)
    await ev(`(() => { const s = document.createElement('style'); s.textContent = '*,*::before,*::after{transition:none!important;animation:none!important;scroll-behavior:auto!important}'; document.head.appendChild(s); return 1; })()`);
    await sleep(300);
    const { root } = await S('DOM.getDocument', { depth: 0 });
    const { nodeIds } = await S('DOM.querySelectorAll', { nodeId: root.nodeId, selector: 'a[href], button, input:not([type=hidden]), select, textarea, summary, [role=button], [tabindex]:not([tabindex="-1"])' });
    const picked = [];
    for (const nodeId of nodeIds) { try { await S('DOM.getBoxModel', { nodeId }); picked.push(nodeId); } catch {} if (picked.length >= 30) break; }
    const forced = {}; const desc = [];
    for (let i = 0; i < picked.length; i++) {
      const nodeId = picked[i];
      try { const d = (await S('DOM.describeNode', { nodeId })).node; const a = d.attributes || []; const g = n => { const k = a.indexOf(n); return k >= 0 ? a[k + 1] : ''; }; desc.push(`${d.nodeName.toLowerCase()}${g('id') ? '#' + g('id') : ''}${g('class') ? '.' + g('class').split(' ').slice(0, 2).join('.') : ''}`); } catch { desc.push('?'); }
      for (const st of [null, 'hover', 'focus', 'focus-visible', 'active']) {
        await S('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: st ? [st] : [] });
        const { computedStyle } = await S('CSS.getComputedStyleForNode', { nodeId });
        forced[`${i}:${st || 'rest'}`] = computedStyle.filter(p => !p.name.startsWith('--')).map(p => p.name + ':' + p.value);
      }
      await S('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: [] });
    }
    // 3. keyboard focus rings (10 Tab stops)
    const tab = [];
    if (combo.tab) {
      await ev('window.scrollTo(0, 0); if (document.activeElement && document.activeElement !== document.body) document.activeElement.blur(); 1'); await sleep(300);
      for (let k = 0; k < 10; k++) {
        for (const type of ['keyDown', 'keyUp']) await S('Input.dispatchKeyEvent', { type, key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, nativeVirtualKeyCode: 9 });
        await sleep(200);
        const info = await ev(`(() => { const e = document.activeElement; if (!e || e === document.body) return null; e.scrollIntoView({ block: 'center' }); const r = e.getBoundingClientRect(); const cs = getComputedStyle(e); return { d: e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (e.className && typeof e.className === 'string' ? '.' + e.className.trim().split(/\\s+/).slice(0, 2).join('.') : ''), x: r.x + scrollX, y: r.y + scrollY, w: r.width, h: r.height, outline: cs.outlineStyle + ' ' + cs.outlineWidth + ' ' + cs.outlineColor + ' ' + cs.outlineOffset }; })()`);
        if (!info) { tab.push(null); continue; }
        await sleep(150);
        const clip = { x: Math.max(0, info.x - 10), y: Math.max(0, info.y - 10), width: Math.min(900, info.w + 20), height: Math.min(300, info.h + 20), scale: 1 };
        const shot = (await S('Page.captureScreenshot', { format: 'png', clip })).data;
        tab.push({ d: info.d, outline: info.outline, shot });
      }
    }
    const bad = statuses.filter(([c, t]) => c >= 400 && t !== 'Image').map(([c, t, u]) => `${c} ${u.split('?')[0].slice(-60)}`);
    return { variant, docStatus, bad, print, forced, desc, tab };
  } finally {
    handlers.splice(handlers.indexOf(h), 1);
    await send('Target.closeTarget', { targetId }).catch(() => {});
  }
}

// helper tab for pixel diffs of the focus-ring clips
const { targetId: diffTarget } = await send('Target.createTarget', { url: 'about:blank' });
const { sessionId: diffSession } = await send('Target.attachToTarget', { targetId: diffTarget, flatten: true });
const PIX = `(async (A, B) => { const load = s => new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = 'data:image/png;base64,' + s; }); const [a, b] = await Promise.all([load(A), load(B)]); const w = Math.max(a.width, b.width), h = Math.max(a.height, b.height); const get = img => { const c = new OffscreenCanvas(w, h); const x = c.getContext('2d', { willReadFrequently: true }); x.drawImage(img, 0, 0); return x.getImageData(0, 0, w, h).data; }; const da = get(a), db = get(b); let n = 0, big = 0; for (let i = 0; i < da.length; i += 4) { const d = Math.max(Math.abs(da[i] - db[i]), Math.abs(da[i + 1] - db[i + 1]), Math.abs(da[i + 2] - db[i + 2])); if (d) { n++; if (d > 8) big++; } } return { n, big, a: [a.width, a.height], b: [b.width, b.height] }; })`;
const pix = async (A, B) => A === B ? { n: 0, big: 0 } : (await send('Runtime.evaluate', { expression: `${PIX}(${JSON.stringify(A)}, ${JSON.stringify(B)})`, awaitPromise: true, returnByValue: true, timeout: 60000 }, diffSession)).result.value;

const normGrad = v => v.replace(/ 0%(?=[,)])/g, '').replace(/ 100%(?=\))/g, '');
fs.writeFileSync(outFile, '');
for (const [label, url] of pages) for (const combo of COMBOS) {
  const loads = {};
  for (const variant of VARIANTS) {
    let r = null;
    for (let attempt = 1; attempt <= 3; attempt++) {
      try { r = await loadOnce(variant, url, combo); } catch (e) { r = { error: String(e).slice(0, 160) }; }
      const limited = r.docStatus === 429 || (r.bad || []).some(b => b.startsWith('429'));
      if (!r.error && r.docStatus === 200 && !(r.bad || []).length) break;
      console.log(`  ${label} ${combo.w} ${variant}: attempt ${attempt} invalid (doc ${r.docStatus}, bad ${JSON.stringify((r.bad || []).slice(0, 2))}${r.error ? ' ' + r.error : ''}); cooling down`);
      await sleep(limited ? 75000 : 20000);
    }
    loads[variant] = r; await sleep(12000);
  }
  const rec = { label, url, width: combo.w, scheme: combo.scheme };
  if (VARIANTS.some(v => loads[v].error || loads[v].docStatus !== 200)) { rec.error = 'invalid loads: ' + VARIANTS.map(v => v + ':' + (loads[v].error || loads[v].docStatus)).join(' '); fs.appendFileSync(outFile, JSON.stringify(rec) + '\n'); continue; }
  // print media
  const pv = v => { const P = loads[v].print; return Object.fromEntries(Object.entries(P.keys).map(([k, i]) => [k, P.dict[i]])); };
  const [Ps, Pm, P2] = [pv('src'), pv('min'), pv('src2')]; const names = loads.src.print.names;
  const noisy = new Set(Object.keys(Ps).filter(k => Ps[k] !== P2[k]));
  const pdiff = []; let pmiss = 0, graddiff = 0;
  for (const k of Object.keys(Ps)) { if (noisy.has(k)) continue; if (!(k in Pm)) { pmiss++; continue; } if (Ps[k] !== Pm[k]) { const a = JSON.parse(Ps[k]), b = JSON.parse(Pm[k]); const props = []; for (const sec of ['r', '::before', '::after']) (a[sec] || []).forEach((v, i) => { const w = (b[sec] || [])[i]; if (v !== w) props.push([sec + names[i], v, w]); }); const real = props.filter(([, v, w]) => normGrad(String(v)) !== normGrad(String(w))); if (real.length) pdiff.push({ el: k.split('>').slice(-2).join('>'), props: real.slice(0, 4) }); else graddiff++; } }
  rec.print = { elements: Object.keys(Ps).length, noisy: noisy.size, missing: pmiss, realDiff: pdiff.length, serializationOnly: graddiff, sample: pdiff.slice(0, 6) };
  // forced states
  const F = v => loads[v].forced; const fnoisy = new Set(Object.keys(F('src')).filter(k => F('src')[k].join('|') !== F('src2')[k]?.join('|')));
  const fdiff = []; let fgrad = 0;
  for (const k of Object.keys(F('src'))) { if (fnoisy.has(k)) continue; const a = F('src')[k], b = F('min')[k]; if (!b) continue; if (a.join('|') === b.join('|')) continue; const props = []; a.forEach((x, i) => { if (x !== b[i]) props.push(x.split(':')[0] + ' ' + x.slice(x.indexOf(':') + 1) + ' -> ' + (b[i] || '').slice((b[i] || '').indexOf(':') + 1)); }); const real = props.filter(p => { const [l, r] = p.split(' -> '); return normGrad(l) !== normGrad(r || ''); }); if (real.length) { const [i, st] = k.split(':'); fdiff.push({ el: loads.src.desc[Number(i)], state: st, props: real.slice(0, 3) }); } else fgrad++; }
  rec.forced = { elements: loads.src.desc.length, states: Object.keys(F('src')).length, noisy: fnoisy.size, realDiff: fdiff.length, serializationOnly: fgrad, sample: fdiff.slice(0, 6) };
  // keyboard focus rings
  if (combo.tab) {
    let stops = 0, same = 0, differ = [], nullStops = 0;
    for (let k = 0; k < 10; k++) { const a = loads.src.tab[k], b = loads.min.tab[k], c = loads.src2.tab[k]; if (!a || !b || !c) { nullStops++; continue; } stops++; const ab = await pix(a.shot, b.shot), ac = await pix(a.shot, c.shot); if (a.d !== b.d) differ.push({ stop: k + 1, why: `different element ${a.d} vs ${b.d}` }); else if (ab.n === 0 || (ac.n > 0 && ab.n <= ac.n)) same++; else { differ.push({ stop: k + 1, el: a.d, pixels: ab, noise: ac, outline: [a.outline, b.outline] }); fs.mkdirSync('tabdiff', { recursive: true }); fs.writeFileSync(`tabdiff/${label}-${combo.w}-stop${k + 1}-src.png`, Buffer.from(a.shot, 'base64')); fs.writeFileSync(`tabdiff/${label}-${combo.w}-stop${k + 1}-min.png`, Buffer.from(b.shot, 'base64')); } }
    rec.tab = { stops, identicalOrInsideNoise: same, differ, nullStops };
  }
  fs.appendFileSync(outFile, JSON.stringify(rec) + '\n');
  console.log(`${label} ${combo.w} ${combo.scheme}: print diff ${rec.print.realDiff} (serialization-only ${rec.print.serializationOnly}, noisy ${rec.print.noisy}) | forced diff ${rec.forced.realDiff} of ${rec.forced.states} states (${rec.forced.elements} elements) | tab ${rec.tab ? rec.tab.identicalOrInsideNoise + '/' + rec.tab.stops : '-'}`);
}
ws.close(); chrome.kill(); console.log('done');
