// usage: [WIDTH=390] [CAND=fixtures/blog-header-critical.css] node header-diag.mjs <url> [blocked-sheet-substring]
// Header geometry with and without a stylesheet (default components.css), and with a candidate inline CSS injected into the blocked page. Shows which element changes size when the sheet arrives.
import { spawn } from 'node:child_process'; import fs from 'node:fs'; import path from 'node:path'; import os from 'node:os';
const chromeBin = () => process.env.CHROME_BIN || (() => { const base = path.join(os.homedir(), 'Library/Caches/ms-playwright'); try { for (const d of fs.readdirSync(base).filter(x => /^chromium-\d+$/.test(x)).sort().reverse()) { const p = path.join(base, d, 'chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing'); if (fs.existsSync(p)) return p; } } catch {} return chromeBin(); })();
const URL_ = process.argv[2], BLOCK = process.argv[3] || 'components.css'; const sleep = ms => new Promise(r => setTimeout(r, ms));
const port = 9400 + Math.floor(Math.random() * 400); const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'bd-'));
const chrome = spawn(chromeBin(), [`--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', '--window-position=-2400,0', '--window-size=500,900', 'about:blank'], { stdio: 'ignore' });
let ver; for (let i = 0; i < 50; i++) { try { ver = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json(); break; } catch { await sleep(200); } }
const ws = new WebSocket(ver.webSocketDebuggerUrl); await new Promise(r => ws.addEventListener('open', r));
let id = 0; const pending = {}; const handlers = []; ws.addEventListener('message', m => { const d = JSON.parse(m.data); if (d.id && pending[d.id]) { pending[d.id](d); delete pending[d.id]; } else handlers.forEach(h => h(d)); });
const send = (method, params = {}, sessionId) => new Promise((res, rej) => { const i = ++id; pending[i] = d => d.error ? rej(new Error(d.error.message)) : res(d.result); ws.send(JSON.stringify({ id: i, method, params, sessionId })); });
const EXPR = `(() => { const out = []; const hd = document.querySelector('header') || document.body; const walk = (el, d) => { if (d > 6) return; const r = el.getBoundingClientRect(); const cs = getComputedStyle(el); out.push({ p: el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\\s+/).slice(0, 2).join('.') : ''), d, y: Math.round(r.y), h: Math.round(r.height * 10) / 10, w: Math.round(r.width), disp: cs.display, mt: cs.marginTop, pt: cs.paddingTop, fs: cs.fontSize, lh: cs.lineHeight }); for (const c of el.children) walk(c, d + 1); }; walk(hd, 0); const m = document.querySelector('main'); const mr = m.getBoundingClientRect(); return { mainTop: Math.round(mr.top), headerH: Math.round(hd.getBoundingClientRect().height), els: out }; })()`;
const res = {};
for (const variant of ['normal', 'blocked', ...(process.env.CAND ? ['patched'] : [])]) {
  const { targetId } = await send('Target.createTarget', { url: 'about:blank' }); const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true }); const S = (m, p) => send(m, p, sessionId);
  await send('Target.activateTarget', { targetId }); await S('Page.enable'); await S('Network.enable'); await S('Emulation.setDeviceMetricsOverride', { width: Number(process.env.WIDTH || 390), height: 844, deviceScaleFactor: 2, mobile: Number(process.env.WIDTH || 390) < 800 });
  await S('Network.setCacheDisabled', { cacheDisabled: true });
  if (variant === 'blocked' || variant === 'patched') { await S('Fetch.enable', { patterns: [{ urlPattern: '*' + BLOCK + '*', requestStage: 'Request' }] }); handlers.push(d => { if (d.sessionId === sessionId && d.method === 'Fetch.requestPaused') send('Fetch.failRequest', { requestId: d.params.requestId, errorReason: 'Failed' }, sessionId).catch(() => {}); }); }
  const loaded = new Promise(r => { handlers.push(d => { if (d.sessionId === sessionId && d.method === 'Page.loadEventFired') r(); }); setTimeout(r, 40000); });
  await S('Page.navigate', { url: URL_ }); await loaded; await sleep(3500);
  if (variant === 'patched') { await S('Runtime.evaluate', { expression: `(() => { const s = document.createElement('style'); s.textContent = ${JSON.stringify(fs.readFileSync(process.env.CAND, 'utf8'))}; document.head.appendChild(s); })()` }); await sleep(800); }
  res[variant] = (await S('Runtime.evaluate', { expression: EXPR, returnByValue: true })).result.value; await send('Target.closeTarget', { targetId }).catch(() => {});
}
ws.close(); chrome.kill();
console.log('main top: normal', res.normal.mainTop, '| without', BLOCK, res.blocked.mainTop, '| header height', res.normal.headerH, 'vs', res.blocked.headerH);
console.log('patched (components blocked + candidate): main top', res.patched?.mainTop, 'header height', res.patched?.headerH);
const A = res.normal.els, B = (res.patched || res.blocked).els; const n = Math.min(A.length, B.length); let shown = 0;
for (let i = 0; i < n && shown < 30; i++) { const a = A[i], b = B[i]; if (a.p !== b.p) { console.log('structure differs at', i, a.p, b.p); break; } if ((a.h !== b.h || a.y !== b.y) && a.h > 0) { shown++; console.log(`${'  '.repeat(a.d)}${a.p} | y ${b.y}->${a.y} h ${b.h}->${a.h} disp ${b.disp}->${a.disp} mt ${b.mt}->${a.mt} pt ${b.pt}->${a.pt} fs ${b.fs}->${a.fs} lh ${b.lh}->${a.lh}`); } }
