# Add `color-scheme` declaration so browser UI follows the active style variation

## Problem

When a user activates Ollie's dark style variation (or any future dark-leaning variation), the rendered theme switches to dark mode but the browser's user-agent UI does not. This causes:

- Native form inputs (`<input>`, `<textarea>`, `<select>`) to render with light backgrounds against the dark page
- Browser-rendered scrollbars to remain in light mode
- Chrome/Edge autofill highlights (the bright yellow background) to become visually jarring
- Native date/color/time picker popovers to appear in light mode
- HTML `<dialog>` defaults and similar UA-styled elements to mismatch

The fix is the CSS [`color-scheme`](https://developer.mozilla.org/en-US/docs/Web/CSS/color-scheme) property. It tells the browser which built-in color schemes the page supports, so the browser can render its own UI surfaces accordingly.

## Why this matters for Ollie specifically

Ollie ships multiple style variations including `dark.json`. Users who select dark style variations expect a coherent dark experience, but `color-scheme` is currently absent from every variation in `styles/`, so every variation effectively renders as `color-scheme: light` (the browser default).

## Proposed change

Two small edits, one CSS line each.

### Default `theme.json`

Add to `styles.css`:

```css
:root { color-scheme: light; }
```

### `styles/dark.json`

Add to `styles.css` (and any other dark-leaning variation if/when added):

```css
:root { color-scheme: dark; }
```

That's the whole change. Browser UI now flips with the variation.

## Optional: ship a "follow my OS" variation

Some users prefer their site to follow their operating-system preference rather than picking a fixed variation. This could be a future addition — a `styles/auto.json` variation that combines:

```css
:root { color-scheme: light dark; }
```

with `@media (prefers-color-scheme: dark) { ... }` overrides for the palette. Not strictly required for this fix, but worth mentioning as a follow-up direction.

## References

- MDN: https://developer.mozilla.org/en-US/docs/Web/CSS/color-scheme
- Web.dev: https://web.dev/articles/color-scheme

## I am happy to open a PR

If maintainers agree, I can submit the PR with both file changes and a brief note in `readme.txt` / changelog. Just let me know.
