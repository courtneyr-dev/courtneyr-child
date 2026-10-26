// usage: node highlight-contrast.mjs [origin]   Contrast of every highlight element (.cr-highlight, .sme-highlighter, marker gradients) on home, about, blog and stream at 390 px.
// Five theme states: OS light/dark with no stored choice (data-theme=system, what most visitors get), and stored light/dark. Storage is cleared before each run.
// 'text on highlight' must be >= 4.5; 'text vs page' only matters for gradient (partial) highlights, where the uncovered half of the line sits on the page.
import { spawn } from 'node:child_process'; import fs from 'node:fs'; import path from 'node:path'; import os from 'node:os';
const chromeBin = () => process.env.CHROME_BIN || (() => { const base = path.join(os.homedir(), 'Library/Caches/ms-playwright'); try { for (const d of fs.readdirSync(base).filter(x => /^chromium-\d+$/.test(x)).sort().reverse()) { const p = path.join(base, d, 'chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing'); if (fs.existsSync(p)) return p; } } catch {} console.error('No browser found. Set CHROME_BIN or install Chrome for Testing (npx playwright install chromium); this script never uses /Applications/Google Chrome.app.'); process.exit(1); })();
const ORIGIN = (process.argv[2] || 'https://courtneyr.dev').replace(/\/$/, '');
const pages = [['home', ORIGIN + '/'], ['about', ORIGIN + '/about/'], ['blog', ORIGIN + '/blog/'], ['stream', ORIGIN + '/stream/']];
const modes = [['OS light, default', 'light', null], ['OS dark, default', 'dark', null], ['OS dark, stored dark', 'dark', 'dark'], ['OS light, stored dark', 'light', 'dark'], ['OS dark, stored light', 'dark', 'light']];
const sleep = ms => new Promise(r => setTimeout(r, ms));
const port = 9400 + Math.floor(Math.random() * 400); const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'hl-'));
const chrome = spawn(chromeBin(), [`--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', '--window-position=-2400,0', '--window-size=500,900', 'about:blank'], { stdio: 'ignore' });
let ver; for (let i = 0; i < 50; i++) { try { ver = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json(); break; } catch { await sleep(200); } }
const ws = new WebSocket(ver.webSocketDebuggerUrl); await new Promise(r => ws.addEventListener('open', r));
let id = 0; const pending = {}; const handlers = []; ws.addEventListener('message', m => { const d = JSON.parse(m.data); if (d.id && pending[d.id]) { pending[d.id](d); delete pending[d.id]; } else handlers.forEach(h => h(d)); });
const send = (method, params = {}, sessionId) => new Promise((res, rej) => { const i = ++id; pending[i] = d => d.error ? rej(new Error(d.error.message)) : res(d.result); ws.send(JSON.stringify({ id: i, method, params, sessionId })); });
const EXPR = `(() => {
 const P = s => { const m = s.match(/rgba?\\(([^)]+)\\)/); if (!m) return null; const v = m[1].split(/[ ,\\/]+/).filter(Boolean).map(Number); return { r: v[0], g: v[1], b: v[2], a: v.length > 3 ? v[3] : 1 }; };
 const L = c => { const f = x => { x /= 255; return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); }; return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b); };
 const CR = (a, b) => { const x = L(a), y = L(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
 const blend = (top, bot) => ({ r: top.r * top.a + bot.r * (1 - top.a), g: top.g * top.a + bot.g * (1 - top.a), b: top.b * top.a + bot.b * (1 - top.a), a: 1 });
 const pageBg = el => { for (let e = el; e; e = e.parentElement) { const c = P(getComputedStyle(e).backgroundColor); if (c && c.a > 0) return c.a < 1 ? blend(c, { r: 255, g: 255, b: 255, a: 1 }) : c; } return { r: 255, g: 255, b: 255, a: 1 }; };
 const out = []; const seen = new Set();
 for (const el of document.querySelectorAll('*')) { const cs = getComputedStyle(el); const bi = cs.backgroundImage; const bc = P(cs.backgroundColor);
  const hasText = [...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim().length > 2); const isHl = /highlight|marker|swipe|hl-/.test(String(el.className)) || el.tagName === 'MARK' || (hasText && /linear-gradient/.test(bi) && /rgba\\(0, 0, 0, 0\\)|transparent/.test(bi)) || (hasText && bc && bc.a > 0 && bc.a < 1 && ['EM','SPAN','STRONG','A'].includes(el.tagName) && el.textContent.trim().length < 80);
  if (!isHl || !el.textContent.trim()) continue; const key = el.tagName + el.className + el.textContent.slice(0, 20); if (seen.has(key)) continue; seen.add(key);
  const text = P(cs.color); const bg = pageBg(el.parentElement || el); const stops = (bi.match(/rgba?\\([^)]+\\)/g) || []).map(P).filter(c => c && c.a > 0);
  const solid = bc && bc.a > 0 ? bc : null; const hl = solid || stops[0] || null; const covered = hl ? blend(hl, bg) : null;
  out.push({ tag: el.tagName.toLowerCase(), cls: String(el.className).slice(0, 30), text: el.textContent.trim().slice(0, 28), full: !!solid, gradient: /gradient/.test(bi), vsPage: +CR(text, bg).toFixed(2), vsHighlight: covered ? +CR(text, covered).toFixed(2) : null, bg: [bg.r, bg.g, bg.b].map(Math.round).join(','), color: [text.r, text.g, text.b].map(Math.round).join(',') }); }
 return { theme: document.documentElement.getAttribute('data-theme'), out }; })()`;
for (const [pname, url] of pages) for (const [mname, scheme, stored] of modes) {
  const { targetId } = await send('Target.createTarget', { url: 'about:blank' }); const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true }); const S = (m, p) => send(m, p, sessionId);
  await send('Target.activateTarget', { targetId }); await S('Page.enable'); await S('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await S('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: scheme }] });
  await S('Page.addScriptToEvaluateOnNewDocument', { source: `try{localStorage.removeItem('courtneyr-theme')}catch(e){}` });
  if (stored) { await S('Page.addScriptToEvaluateOnNewDocument', { source: `try{localStorage.setItem('courtneyr-theme','${stored}')}catch(e){}` }); }
  const loaded = new Promise(r => { handlers.push(d => { if (d.sessionId === sessionId && d.method === 'Page.loadEventFired') r(); }); setTimeout(r, 40000); });
  await S('Page.navigate', { url }); await loaded; await sleep(2500);
  const v = (await S('Runtime.evaluate', { expression: EXPR, returnByValue: true })).result.value; await send('Target.closeTarget', { targetId }).catch(() => {});
  console.log(`${pname} | ${mname} | data-theme=${v.theme} | ${v.out.length ? '' : 'no highlight elements'}`);
  for (const o of v.out) console.log(`    ${o.tag}.${o.cls} "${o.text}" ${o.full ? 'solid' : o.gradient ? 'gradient (partial)' : '-'} | text ${o.color} on page ${o.bg}: ${o.vsPage}:1 | text on highlight: ${o.vsHighlight}:1`);
}
ws.close(); chrome.kill();
