// usage: node pixview.mjs <name> ... compares tabdiff/<name>-src.png with tabdiff/<name>-min.png and writes view-<name>.png (source | other | difference x12).
import { spawn } from 'node:child_process'; import fs from 'node:fs'; import path from 'node:path'; import os from 'node:os';
const names = process.argv.slice(2); const sleep = ms => new Promise(r => setTimeout(r, ms));
const port = 9400 + Math.floor(Math.random() * 400); const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'pv-'));
const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', [`--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', '--window-position=-2400,0', 'about:blank'], { stdio: 'ignore' });
let ver; for (let i = 0; i < 50; i++) { try { ver = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json(); break; } catch { await sleep(200); } }
const ws = new WebSocket(ver.webSocketDebuggerUrl); await new Promise(r => ws.addEventListener('open', r));
let id = 0; const pending = {}; ws.addEventListener('message', m => { const d = JSON.parse(m.data); if (d.id && pending[d.id]) { pending[d.id](d); delete pending[d.id]; } });
const send = (method, params = {}, sessionId) => new Promise((res, rej) => { const i = ++id; pending[i] = d => d.error ? rej(new Error(d.error.message)) : res(d.result); ws.send(JSON.stringify({ id: i, method, params, sessionId })); });
const { targetId } = await send('Target.createTarget', { url: 'about:blank' }); const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
const FN = `(async (A, B) => { const load = s => new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = 'data:image/png;base64,' + s; }); const [a, b] = await Promise.all([load(A), load(B)]); const w = a.width, h = a.height; const get = img => { const c = new OffscreenCanvas(w, h); const x = c.getContext('2d', { willReadFrequently: true }); x.drawImage(img, 0, 0); return x.getImageData(0, 0, w, h).data; }; const da = get(a), db = get(b); const px = []; let mx = 0; const out = new OffscreenCanvas(w * 3 + 20, h); const ox = out.getContext('2d'); ox.fillStyle = '#f0f'; ox.fillRect(0, 0, out.width, out.height); ox.drawImage(a, 0, 0); ox.drawImage(b, w + 10, 0); const dd = ox.createImageData(w, h); for (let i = 0; i < da.length; i += 4) { const d = Math.max(Math.abs(da[i] - db[i]), Math.abs(da[i + 1] - db[i + 1]), Math.abs(da[i + 2] - db[i + 2])); dd.data[i] = dd.data[i + 1] = dd.data[i + 2] = Math.min(255, d * 12); dd.data[i + 3] = 255; if (d) { mx = Math.max(mx, d); const k = i / 4; px.push([k % w, Math.floor(k / w), [da[i], da[i + 1], da[i + 2]], [db[i], db[i + 1], db[i + 2]]]); } } ox.putImageData(dd, 2 * w + 20, 0); const blob = await out.convertToBlob({ type: 'image/png' }); const buf = new Uint8Array(await blob.arrayBuffer()); let s = ''; for (const v of buf) s += String.fromCharCode(v); return { size: [w, h], mx, count: px.length, px: px.slice(0, 12), png: btoa(s) }; })`;
for (const n of names) {
  const A = fs.readFileSync(`tabdiff/${n}-src.png`).toString('base64'), B = fs.readFileSync(`tabdiff/${n}-min.png`).toString('base64');
  const r = (await send('Runtime.evaluate', { expression: `${FN}(${JSON.stringify(A)}, ${JSON.stringify(B)})`, awaitPromise: true, returnByValue: true }, sessionId)).result.value;
  fs.writeFileSync(`view-${n}.png`, Buffer.from(r.png, 'base64'));
  console.log(n, 'px', r.count, 'maxDelta', r.mx);
}
ws.close(); chrome.kill();
