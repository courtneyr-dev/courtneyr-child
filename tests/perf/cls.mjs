// usage: node cls.mjs <labels-and-urls.txt> <out.ndjson> <loads-per-url> [only-label,...]
// Sequential, throttled loads (1.6 Mbps, 150 ms RTT, 4x CPU, mobile viewport, natural user agent).
// Records FCP, LCP, layout shifts (with sources) before and after one mouse move, and stylesheet/script transfer.
import { spawn } from 'node:child_process';
import fs from 'node:fs'; import path from 'node:path'; import os from 'node:os';

const [urlFile, outFile, loadsArg, only] = process.argv.slice(2);
let urls = fs.readFileSync(urlFile, 'utf8').trim().split('\n').map(l => l.split(' '));
if (only) { const s = new Set(only.split(',')); urls = urls.filter(([l]) => s.has(l)); }
const LOADS = Number(loadsArg || 3);
const sleep = ms => new Promise(r => setTimeout(r, ms));
const port = 9400 + Math.floor(Math.random() * 400);
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'cls-profile-'));
const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  [`--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', '--disable-extensions', '--disable-sync', '--window-position=-2400,0', '--window-size=500,900', 'about:blank'], { stdio: 'ignore' });
let ver;
for (let i = 0; i < 50; i++) { try { ver = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json(); break; } catch { await sleep(200); } }
if (!ver) { chrome.kill(); throw new Error('chrome did not start'); }
const ws = new WebSocket(ver.webSocketDebuggerUrl);
await new Promise(r => ws.addEventListener('open', r));
let id = 0; const pending = {}; const handlers = [];
ws.addEventListener('message', m => { const d = JSON.parse(m.data); if (d.id && pending[d.id]) { pending[d.id](d); delete pending[d.id]; } else handlers.forEach(h => h(d)); });
const send = (method, params = {}, sessionId) => new Promise((res, rej) => { const i = ++id; pending[i] = d => d.error ? rej(new Error(method + ': ' + d.error.message)) : res(d.result); ws.send(JSON.stringify({ id: i, method, params, sessionId })); });

const PROBE = `(() => {
  window.__m = { lcp: [], fcp: null, ls: [], nav: null };
  const nm = n => n ? (n.nodeName || '').toLowerCase() + (n.id ? '#' + n.id : '') + (typeof n.className === 'string' && n.className ? '.' + n.className.trim().split(/\\s+/).slice(0, 2).join('.') : '') : null;
  new PerformanceObserver(l => { for (const e of l.getEntries()) window.__m.lcp.push({ t: Math.round(e.startTime), size: e.size, el: e.element ? nm(e.element) : (e.url || null) }); }).observe({ type: 'largest-contentful-paint', buffered: true });
  new PerformanceObserver(l => { for (const e of l.getEntries()) if (e.name === 'first-contentful-paint') window.__m.fcp = Math.round(e.startTime); }).observe({ type: 'paint', buffered: true });
  new PerformanceObserver(l => { for (const e of l.getEntries()) window.__m.ls.push({ t: Math.round(e.startTime), v: +e.value.toFixed(5), input: e.hadRecentInput, src: (e.sources || []).slice(0, 3).map(s => nm(s.node) + ' ' + Math.round(s.previousRect.y) + '->' + Math.round(s.currentRect.y)) }); }).observe({ type: 'layout-shift', buffered: true });
})();`;

async function oneLoad(url, width) {
  const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
  const S = (m, p) => send(m, p, sessionId);
  const reqs = {}; let first = null; let tInteractWall = null;
  const h = d => {
    if (d.sessionId !== sessionId) return; const p = d.params;
    if (d.method === 'Network.requestWillBeSent') { if (first === null) first = p.timestamp; reqs[p.requestId] = { url: p.request.url, type: p.type, wall: p.wallTime, t0: p.timestamp }; }
    if (d.method === 'Network.responseReceived' && reqs[p.requestId]) { reqs[p.requestId].status = p.response.status; reqs[p.requestId].type = p.type; }
    if (d.method === 'Network.loadingFinished' && reqs[p.requestId]) { reqs[p.requestId].enc = p.encodedDataLength; reqs[p.requestId].t1 = p.timestamp; }
  };
  handlers.push(h);
  try {
    await send('Target.activateTarget', { targetId });
    await S('Emulation.setFocusEmulationEnabled', { enabled: true });
    await S('Emulation.setDeviceMetricsOverride', { width, height: 844, deviceScaleFactor: 2, mobile: true });
    for (const d of ['Network', 'Page', 'Runtime']) await S(d + '.enable');
    await S('Network.setCacheDisabled', { cacheDisabled: true });
    await S('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 1.6 * 1024 * 1024 / 8, uploadThroughput: 750 * 1024 / 8 });
    await S('Emulation.setCPUThrottlingRate', { rate: 4 });
    await S('Page.addScriptToEvaluateOnNewDocument', { source: PROBE });
    const loaded = new Promise(r => { const lh = d => { if (d.sessionId === sessionId && d.method === 'Page.loadEventFired') r(); }; handlers.push(lh); setTimeout(r, 60000); });
    await S('Page.navigate', { url });
    await loaded; await sleep(10000);
    const ev = async e => (await S('Runtime.evaluate', { expression: e, returnByValue: true })).result.value;
    const vis = await ev('document.visibilityState + " paint entries: " + performance.getEntriesByType("paint").map(e => e.name + "@" + Math.round(e.startTime)).join(",")');
    if (process.env.CLS_DEBUG) console.log('   visibility:', vis);
    const A = JSON.parse(await ev('JSON.stringify(window.__m)'));
    tInteractWall = Date.now() / 1000;
    await S('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 120, y: 260 }); await sleep(250);
    await S('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 200, y: 330 });
    await sleep(6000);
    const B = JSON.parse(await ev('JSON.stringify(window.__m)'));
    const R = Object.values(reqs);
    const doc = R.find(r => r.type === 'Document');
    const theme = R.filter(r => r.url.includes('/themes/courtneyr-child/'));
    const bad = R.filter(r => r.status >= 400 && r.type !== 'Image').map(r => `${r.status} ${r.url.split('?')[0].slice(-60)}`);
    const bytes = (arr, pred) => arr.filter(pred).reduce((n, r) => n + (r.enc || 0), 0);
    const afterIx = r => r.wall >= tInteractWall - 0.5;
    const sheets = R.filter(r => r.type === 'Stylesheet');
    return {
      docStatus: doc?.status, bad, fcp: B.fcp, lcp: B.lcp.at(-1) ?? null, lcpCandidates: B.lcp.length,
      shiftsA: A.ls.filter(s => !s.input), shiftsAll: B.ls.filter(s => !s.input),
      reqs: R.length, sheetReqs: sheets.length, sheetBytes: bytes(sheets, () => true), themeSheetReqs: theme.filter(r => r.type === 'Stylesheet').length,
      themeSheetBytes: bytes(theme, r => r.type === 'Stylesheet'), themeJsBytes: bytes(theme, r => r.type === 'Script'), scriptBytes: bytes(R, r => r.type === 'Script'),
      sheetBytesAfterInteraction: bytes(sheets, afterIx), sheetReqsAfterInteraction: sheets.filter(afterIx).length,
      themeSheetEnds: theme.filter(r => r.type === 'Stylesheet' && r.t1).map(r => [r.url.split('/assets/css/')[1]?.split('?')[0], Math.round((r.t1 - first) * 1000)]).sort((a, b) => a[1] - b[1]),
    };
  } finally {
    handlers.splice(handlers.indexOf(h), 1);
    await send('Target.closeTarget', { targetId }).catch(() => {});
  }
}

const cls = shifts => ({ sum: +shifts.reduce((n, s) => n + s.v, 0).toFixed(4), max: +Math.max(0, ...shifts.map(s => s.v)).toFixed(4) });
fs.writeFileSync(outFile, '');
for (const [label, url] of urls) {
  for (let n = 1; n <= LOADS; n++) {
    let rec = null, attempts = 0;
    while (attempts < 4) {
      attempts++;
      try { rec = await oneLoad(url, 390); } catch (e) { rec = { error: String(e).slice(0, 160) }; }
      const limited = rec.docStatus === 429 || (rec.bad || []).some(b => b.startsWith('429'));
      if (!rec.error && rec.docStatus === 200 && !limited && !(rec.bad || []).length) break;
      console.log(`  ${label} load ${n}: attempt ${attempts} invalid (doc ${rec.docStatus}, bad ${JSON.stringify((rec.bad || []).slice(0, 2))}${rec.error ? ', ' + rec.error : ''}); cooling down`);
      await sleep(limited ? 75000 : 20000);
    }
    const out = { label, url, load: n, attempts, ...rec, clsA: rec.shiftsA ? cls(rec.shiftsA) : null, clsAll: rec.shiftsAll ? cls(rec.shiftsAll) : null };
    fs.appendFileSync(outFile, JSON.stringify(out) + '\n');
    console.log(`${label} #${n} doc ${rec.docStatus} FCP ${rec.fcp} LCP ${rec.lcp?.t} CLS pre-interaction ${out.clsA?.sum} total ${out.clsAll?.sum} theme sheets ${rec.themeSheetReqs}/${rec.themeSheetBytes} B (attempts ${attempts})`);
    await sleep(15000);
  }
  await sleep(10000);
}
ws.close(); chrome.kill(); console.log('done');
