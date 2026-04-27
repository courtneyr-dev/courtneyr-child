# PR: Add `color-scheme` declaration so browser UI follows the active style variation

## Summary

Tells the browser which built-in color scheme each Ollie style variation supports, so user-agent UI surfaces (form controls, scrollbars, autofill highlights, native date pickers, `<dialog>` defaults) flip cohesively when a user activates a dark variation.

Closes #ISSUE_NUMBER

## Changes

### `theme.json`

In the top-level `styles` object, append `color-scheme: light;` to `css`:

```diff
   "styles": {
-    "css": "body { ... existing css ... }",
+    "css": "body { ... existing css ... } :root { color-scheme: light; }",
     ...
   }
```

(If `styles.css` does not yet exist, add it as a string with the single declaration.)

### `styles/dark.json`

Same pattern, value `dark`:

```diff
   "styles": {
-    "css": "... existing dark css ...",
+    "css": "... existing dark css ... :root { color-scheme: dark; }",
     ...
   }
```

If Ollie has additional dark-leaning or "high contrast dark" variations, repeat the same one-line addition there.

### `readme.txt` / changelog

```
= Unreleased =
* Added `color-scheme` declaration to the default and dark style variations so browser-rendered UI surfaces (form controls, scrollbars, autofill highlights) match the active variation. Improves consistency for users on dark variations.
```

## Test checklist

In Chrome, Firefox, and Safari:

- [ ] Default variation: `<input type="text">` renders with light background, scrollbars are light
- [ ] Dark variation: `<input type="text">` renders with dark background, scrollbars are dark
- [ ] Switching variation in the Site Editor and reloading the front-end flips the form-control rendering
- [ ] Autofilled fields in Chrome no longer have the bright-yellow highlight when on the dark variation
- [ ] `<input type="date">` picker renders in the appropriate scheme on each variation
- [ ] No regression in light mode — visuals identical to pre-PR

## Why not put `color-scheme` in the body selector?

`color-scheme` propagates to descendants from the element it is declared on, but the browser also uses it to color the canvas (the area outside `<html>`) and the scrollbar. Setting it on `:root` (i.e. `<html>`) is the canonical, well-defined location. The CSS Working Group spec explicitly recommends `:root` for this reason.

## Notes for reviewers

- This is purely additive CSS — no JS, no PHP, no template changes.
- No migration needed; existing sites pick up the new behavior on their next theme load.
- The change is forward-compatible with WP core's `theme.json` `styles.css` field, which has been stable since WP 6.2.
