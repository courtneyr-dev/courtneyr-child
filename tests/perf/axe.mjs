// usage: [WIDTH=390] [SCHEME=light|dark] node axe.mjs <url> [<url> ...]   WCAG 2.0/2.1/2.2 A and AA with the repo's pinned axe-core, headed Chrome, natural user agent.
// WIDTH: viewport width in px (default 1280; under 768 it emulates a 2x mobile viewport). SCHEME: forces prefers-color-scheme through Emulation.setEmulatedMedia, so a 'light' run is light even when macOS is in dark mode.
// With neither set the output is unchanged and the page follows the Mac's appearance. With either set, a 'state' line reports the width and scheme the page actually saw.
import { spawn } from 'node:child_process'; import fs from 'node:fs'; import path from 'node:path'; import os from 'node:os'; import { fileURLToPath } from 'node:url';
const chromeBin = () => process.env.CHROME_BIN || (() => { const base = path.join(os.homedir(), 'Library/Caches/ms-playwright'); try { for (const d of fs.readdirSync(base).filter(x => /^chromium-\d+$/.test(x)).sort().reverse()) { const p = path.join(base, d, 'chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing'); if (fs.existsSync(p)) return p; } } catch {} console.error('No browser found. Set CHROME_BIN or install Chrome for Testing (npx playwright install chromium); this script never uses /Applications/Google Chrome.app.'); process.exit(1); })();
const urls = process.argv.slice(2); const W = Number(process.env.WIDTH || 1280); const SCHEME = process.env.SCHEME || '';
if (!(W >= 200 && W <= 3840)) { console.error(`WIDTH must be a number from 200 to 3840, got "${process.env.WIDTH}"`); process.exit(1); }
if (SCHEME && !['light', 'dark'].includes(SCHEME)) { console.error(`SCHEME must be light or dark, got "${SCHEME}"`); process.exit(1); }
const sleep = ms => new Promise(r => setTimeout(r, ms));
const axeSrc = fs.readFileSync(path.join(path.dirname(fileURLToPath(import.meta.url)), '../../node_modules/axe-core/axe.min.js'), 'utf8');
const port = 9400 + Math.floor(Math.random() * 400); const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'axe-'));
const chrome = spawn(chromeBin(), [`--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', '--window-position=-2400,0', '--window-size=1280,900', 'about:blank'], { stdio: 'ignore' });
let ver; for (let i = 0; i < 50; i++) { try { ver = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json(); break; } catch { await sleep(200); } }
const ws = new WebSocket(ver.webSocketDebuggerUrl); await new Promise(r => ws.addEventListener('open', r));
let id = 0; const pending = {}; const handlers = []; ws.addEventListener('message', m => { const d = JSON.parse(m.data); if (d.id && pending[d.id]) { pending[d.id](d); delete pending[d.id]; } else handlers.forEach(h => h(d)); });
const send = (method, params = {}, sessionId) => new Promise((res, rej) => { const i = ++id; pending[i] = d => d.error ? rej(new Error(d.error.message)) : res(d.result); ws.send(JSON.stringify({ id: i, method, params, sessionId })); });
for (const url of urls) {
  const { targetId } = await send('Target.createTarget', { url: 'about:blank' }); const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true }); const S = (m, p) => send(m, p, sessionId);
  await send('Target.activateTarget', { targetId }); await S('Page.enable'); await S('Emulation.setDeviceMetricsOverride', { width: W, height: W < 768 ? 844 : 900, deviceScaleFactor: W < 768 ? 2 : 1, mobile: W < 768 }); if (SCHEME) await S('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: SCHEME }] });
  const loaded = new Promise(r => { handlers.push(d => { if (d.sessionId === sessionId && d.method === 'Page.loadEventFired') r(); }); setTimeout(r, 40000); });
  await S('Page.navigate', { url }); await loaded; await sleep(3000);
  await S('Runtime.evaluate', { expression: axeSrc });
  const r = await S('Runtime.evaluate', { expression: `axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } }).then(x => JSON.stringify({ v: x.violations.map(v => ({ id: v.id, impact: v.impact, n: v.nodes.length, sample: v.nodes[0].target.join(' ').slice(0, 90) })), pass: x.passes.length, incomplete: x.incomplete.length }))`, awaitPromise: true, returnByValue: true });
  const o = JSON.parse(r.result.value);
  if (process.env.WIDTH || SCHEME) { const st = await S('Runtime.evaluate', { expression: `JSON.stringify({ w: innerWidth, dark: matchMedia('(prefers-color-scheme: dark)').matches, dt: document.documentElement.dataset.theme || null })`, returnByValue: true }); console.log('   state', st.result.value); }
  console.log(url, '| violations', o.v.length, '| passes', o.pass, '| incomplete', o.incomplete); for (const v of o.v) console.log('   ', v.id, v.impact, 'x' + v.n, v.sample);
  await send('Target.closeTarget', { targetId }).catch(() => {});
}
ws.close(); chrome.kill();
