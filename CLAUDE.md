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
- `templates/_archive` and `parts/_archive` are live registrations until they leave those folders.

## Conventions

- Commits: Emoji-Log, imperative, version in `style.css` and `functions.php` bumped per release.
- Never push or deploy without an explicit go; deploy repos pin this repo by SHA.
- CSS: tokens come from theme.json presets; no new literal colors, tilts or letter-spacing values; artifact styles get their own file in the shape of `assets/css/cr-mood-pin.css`.
