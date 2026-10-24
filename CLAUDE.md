# courtneyr-child

Block child theme of Ollie for courtneyr.dev. Presentation only: the Post Kinds for IndieWeb plugin owns kind data, semantics, bindings, abilities and microformats; this theme never writes post meta or terms.

## WORDPRESS ARCHITECTURE RULE

For WordPress implementation work:

1. Inspect Ollie through Ollie MCP (or read the Ollie source when the MCP is not wired in: `templates/`, `parts/`, `patterns/`, `theme.json`, `functions.php`, `assets/styles/`).
2. Inspect relevant blocks/APIs through Block MCP (or the local site's REST: `wp/v2/block-types`, `wp/v2/block-patterns/patterns`, `wp/v2/templates`, `wp/v2/template-parts`, `wp-abilities/v1/abilities`).
3. Reproduce/test the real workflow in WordPress Studio (until a courtneyr.dev Studio site exists, the wp-env instance on `localhost:8892` defined in `~/projects/mood-pin-env/.wp-env.json`, which mounts this checkout).
4. Prefer, in order where appropriate:
   - theme.json/block supports
   - block style
   - block variation
   - pattern
   - pattern override
   - Block Bindings
   - template/template part
   - existing dynamic block
   - small integration PHP
   - custom JS/Interactivity API
5. Do not create bespoke markup or another custom block until the native options have been evaluated.
6. Preserve plugin data ownership, IndieWeb semantics, accessibility, and editor/frontend parity.
7. Record the architectural decision when choosing a custom implementation over a native WordPress primitive.

Project policy note (in Courtney's vault, not in this repo; written 2026-09-12 by a separate session): `1. Projects/courtneyr.dev/wordpress-native-architecture/CourtneyR-WordPress-Architecture-First-Standard.md`. Read it in full before relying on it; if the vault is unavailable, say so rather than claiming the gate was checked.

The ordering is a decision framework, not dogma. Stamps, the mood pin renderer, Able Player routing and the Stream card adapters are recorded as justified custom code. Decisions, the audit matrix and the roadmap live in the vault: `1. Projects/courtneyr.dev/wordpress-native-architecture/`.

## Per-migration report

```
Ollie primitive evaluated / Reused / Extended / Rejected / Reason
Core or custom blocks considered / Variations / Styles / Bindings / Patterns considered
Selected mechanism / Why
Custom code replaced / Custom code kept and why
Evidence: editor, frontend, mobile, dark, axe, console
```

## Verification loop (every change)

Baseline first, then change, then compare: frontend at 1440 and 375, dark (`localStorage courtneyr-theme=dark`), 200 % zoom proxy (720 px viewport), console, axe, DOM order, keyboard. `scratchpad/evidence/shoot.mjs` from the 2026-09-12 audit is the reference harness (Playwright from the plugin's `node_modules`, axe-core injected). Measure geometry with a script, not by eye: a template-part wrapper inside a constrained group changes how `alignfull` children resolve.

## Layout gotchas learned

- `theme.json` `templateParts` merges with Ollie's by array index; a child that declares any part must re-declare `header`, `footer`, `sidebar` in Ollie's order first.
- The theme's full-bleed rules key off `.alignfull` on the Group (`width: 100vw; margin-left: calc(50% - 50vw)`); wrap such a group in a plain `wp:template-part` (no `align`) and leave the group unchanged.
- Archived templates and parts live in `_archive/templates` and `_archive/parts`. Core registers every `.html` file under `templates/` and `parts/` recursively, so never move them back.

## CSS performance guardrails

Measured 2026-10-22/23 (full notes: vault, `1. Projects/Post Kinds for IndieWeb/theme-performance-guardrails-2026-10-23.md`). `npm run lint:css-guardrails` enforces the first four.

- A kind gets its own sheet, `assets/css/cr-<kind>.css`, handle `courtneyr-<kind>`. `cr-post-kinds.css` (391,929 B, home page uses 9%) holds only what every kind shares and may not grow.
- Name a kind's classes with a root nothing else uses. One kind per selector list: `.kind-watch, .kind-read` defeats the pruning engine, which drops a sheet only when its distinctive classes did not render.
- A sheet is at most 30 KiB. A sheet that loads on some pages only goes in `RUCSS_INLINE_HANDLES` in `inc/stream-style-pruning.php`; Perfmatters builds one used CSS per URL type from the first page it sees.
- A dark-mode rule keyed to `:root:not([data-theme])` also needs `[data-theme="system"]` in its selector list. `no-flash.js` writes `data-theme="system"` for every visitor with no stored choice, so a bare missing-attribute rule never matches. Write `:root:not([data-theme="light"])`.
- `path` on a registered block style makes WordPress inline it when it fits the shared 40,000 B budget (core and Ollie sheets count too), which flips `<link>` to `<style>` on some pages.
- Not enforced by code: keep first-paint layout out of Perfmatters-delayed sheets; after any used-CSS clear, load one representative page per type first (a post with a figure, an `hr` and a featured image) or rules drop and CLS moves; PageSpeed's mobile LCP is a simulation, so report applied-throttle numbers beside it.
- Never turn on Perfmatters' own CSS minify for this theme. Deploys minify with `tools/minify-css.mjs`; rollback is `git revert` of the sync commit in the deploy repo.

## Conventions

- Commits: Emoji-Log, imperative, version in `style.css` and `functions.php` bumped per release.
- Never push or deploy without an explicit go; deploy repos pin this repo by SHA.
- CSS: tokens come from theme.json presets; no new literal colors, tilts or letter-spacing values; artifact styles get their own file in the shape of `assets/css/cr-mood-pin.css`.
