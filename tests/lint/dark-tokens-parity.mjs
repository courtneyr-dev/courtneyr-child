// Both dark palettes in tokens.css must define the same custom properties:
// the toggle block (`:root[data-theme="dark"]`) and the OS block
// (`@media (prefers-color-scheme: dark)` with `:root:not([data-theme="light"])`).
import { readFileSync } from 'node:fs';
const css = readFileSync(new URL('../../assets/css/tokens.css', import.meta.url), 'utf8');
// Take the LAST match, not the first: tokens.css also has a one-line
// `:root[data-theme="dark"] { color-scheme: dark; }` rule (the UA
// color-scheme declarations, earlier in the file) that matches the same
// selector text as the real token-reassignment block. The first match
// would find that near-empty rule instead of the block we care about.
const block = (startRe) => {
	const re = new RegExp(startRe.source, startRe.flags.includes('g') ? startRe.flags : startRe.flags + 'g');
	let match, last = null;
	while ((match = re.exec(css))) last = match;
	if (!last) return null;
	let depth = 0, i = css.indexOf('{', last.index);
	for (let j = i; j < css.length; j++) {
		if (css[j] === '{') depth++;
		if (css[j] === '}' && --depth === 0) return css.slice(i, j + 1);
	}
	return null;
};
const props = (s) => new Set([...s.matchAll(/(--cr-[a-z0-9-]+)\s*:/g)].map((m) => m[1]));
const toggle = block(/:root\[data-theme="dark"\]\s*\{/);
const media = block(/@media \(prefers-color-scheme: dark\)/);
if (!toggle || !media) { console.error('dark-tokens-parity: could not find both dark blocks'); process.exit(1); }
const a = props(toggle), b = props(media);
const onlyToggle = [...a].filter((p) => !b.has(p)), onlyMedia = [...b].filter((p) => !a.has(p));
if (onlyToggle.length || onlyMedia.length) {
	console.error('dark-tokens-parity: mismatch\n  only in [data-theme=dark]:', onlyToggle.join(', ') || '(none)', '\n  only in prefers-color-scheme:', onlyMedia.join(', ') || '(none)');
	process.exit(1);
}
console.log(`dark-tokens-parity: ${a.size} tokens match`);
