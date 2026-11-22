// Captures real WordPress pages from the local Studio site: the served HTML (raw bytes, as curl gets them)
// and every same-origin asset the page loads in Chrome. usage: node capture-pages.mjs <base|head> <urls.tsv> <outdir>
import { chromium } from '@playwright/test';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
const [, , mode, tsv, outdir] = process.argv;
const ORIGIN = 'http://localhost:8887';
const rows = readFileSync(tsv, 'utf8').trim().split('\n').map((l) => l.split('\t'));
mkdirSync(join(outdir, mode, 'pages'), { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CR_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
const seen = new Map();
for (const [name, url] of rows) {
	const res = await fetch(url, { redirect: 'follow' });
	writeFileSync(join(outdir, mode, 'pages', name + '.html'), Buffer.from(await res.arrayBuffer()));
	const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
	page.on('response', async (r) => {
		const u = new URL(r.url());
		if (u.origin !== ORIGIN || r.request().method() !== 'GET' || r.status() !== 200) return;
		const type = r.headers()['content-type'] || '';
		if (/text\/html/.test(type)) return;
		if (!seen.has(u.pathname)) {
			try { seen.set(u.pathname, { body: await r.body(), type }); } catch { /* aborted */ }
		}
	});
	await page.goto(url, { waitUntil: 'networkidle' });
	await page.close();
}
await browser.close();
const manifest = {};
for (const [path, { body, type }] of seen) {
	const file = join(outdir, mode, 'assets', path.replace(/^\//, ''));
	mkdirSync(dirname(file), { recursive: true });
	writeFileSync(file, body);
	manifest[path] = type;
}
writeFileSync(join(outdir, mode, 'assets-manifest.json'), JSON.stringify(manifest, null, 1));
console.log(mode, rows.length, 'pages', seen.size, 'assets');
